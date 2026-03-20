<?php

declare (strict_types=1);
/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2013 Jonathan Vollebregt (jnvsor@gmail.com), Rokas Šleinius (raveren@gmail.com)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 * IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
namespace Kint\Renderer;

use Kint\Renderer\Rich\Tab_Plugin_Interface;
use Kint\Renderer\Rich\Value_Plugin_Interface;
use Kint\Utils;
use Kint\Value\Abstract_Value;
use Kint\Value\Context\Class_Declared_Context;
use Kint\Value\Context\Context_Interface;
use Kint\Value\Context\Property_Context;
use Kint\Value\Instance_Value;
use Kint\Value\Representation;
use Kint\Value\Representation\Container_Representation;
use Kint\Value\Representation\Representation_Interface;
use Kint\Value\Representation\String_Representation;
use Kint\Value\Representation\Value_Representation;
use Kint\Value\String_Value;
/**
 * @psalm-import-type Encoding from StringValue
 */
class Rich_Renderer extends Abstract_Renderer
{
    use Asset_Renderer_Trait;
    /**
     * RichRenderer value plugins should implement ValuePluginInterface.
     *
     * @psalm-var class-string<ValuePluginInterface>[]
     */
    public static array $value_plugins = ['array_limit' => Rich\Lock_Plugin::class, 'blacklist' => Rich\Lock_Plugin::class, 'callable' => Rich\Callable_Plugin::class, 'color' => Rich\Color_Plugin::class, 'depth_limit' => Rich\Lock_Plugin::class, 'recursion' => Rich\Lock_Plugin::class, 'trace_frame' => Rich\Trace_Frame_Plugin::class];
    /**
     * RichRenderer tab plugins should implement TabPluginInterface.
     *
     * @psalm-var array<string, class-string<TabPluginInterface>>
     */
    public static array $tab_plugins = ['binary' => Rich\Binary_Plugin::class, 'callable' => Rich\Callable_Definition_Plugin::class, 'color' => Rich\Color_Plugin::class, 'microtime' => Rich\Microtime_Plugin::class, 'profiling' => Rich\Profile_Plugin::class, 'source' => Rich\Source_Plugin::class, 'table' => Rich\Table_Plugin::class];
    public static array $pre_render_sources = ['script' => [[self::class, 'renderJs']], 'style' => [[self::class, 'renderCss']], 'raw' => []];
    /**
     * The maximum length of a string before it is truncated.
     *
     * Falsey to disable
     */
    public static int $strlen_max = 80;
    /**
     * Timestamp to print in footer in date() format.
     */
    public static ?string $timestamp = null;
    /**
     * Whether or not to render access paths.
     *
     * Access paths can become incredibly heavy with very deep and wide
     * structures. Given mostly public variables it will typically make
     * up one quarter of the output HTML size.
     *
     * If this is an unacceptably large amount and your browser is groaning
     * under the weight of the access paths - your first order of buisiness
     * should be to get a new browser. Failing that, use this to turn them off.
     */
    public static bool $access_paths = true;
    /**
     * Assume types and sizes don't need to be escaped.
     *
     * Turn this off if you use anything but ascii in your class names,
     * but it'll cause a slowdown of around 10%
     */
    public static bool $escape_types = false;
    /**
     * Move all dumps to a folder at the bottom of the body.
     */
    public static bool $folder = false;
    public static bool $needs_pre_render = true;
    public static bool $always_pre_render = false;
    protected array $plugin_objs = [];
    protected bool $expand = false;
    protected bool $force_pre_render = false;
    protected bool $use_folder = false;
    public function __construct()
    {
        parent::__construct();
        self::$theme ??= 'original.css';
        $this->use_folder = self::$folder;
        $this->force_pre_render = self::$always_pre_render;
    }
    public function set_call_info(array $info): void
    {
        parent::set_call_info($info);
        if (\in_array('!', $info['modifiers'], true)) {
            $this->expand = true;
            $this->use_folder = false;
        }
        if (\in_array('@', $info['modifiers'], true)) {
            $this->force_pre_render = true;
        }
    }
    public function set_statics(array $statics): void
    {
        parent::set_statics($statics);
        if (!empty($statics['expanded'])) {
            $this->expand = true;
        }
        if (!empty($statics['return'])) {
            $this->force_pre_render = true;
        }
    }
    public function should_pre_render(): bool
    {
        return $this->force_pre_render || self::$needs_pre_render;
    }
    public function render(Abstract_Value $v): string
    {
        $render_spl_ids_stash = $this->render_spl_ids;
        if ($this->render_spl_ids && $v->flags & Abstract_Value::FLAG_GENERATED) {
            $this->render_spl_ids = false;
        }
        if ($plugin = $this->get_value_plugin($v)) {
            $output = $plugin->render_value($v);
            if (null !== $output && \strlen($output)) {
                if (!$this->render_spl_ids && $render_spl_ids_stash) {
                    $this->render_spl_ids = true;
                }
                return $output;
            }
        }
        $children = $this->render_children($v);
        $header = $this->render_header_wrapper($v->get_context(), (bool) \strlen($children), $this->render_header($v));
        if (!$this->render_spl_ids && $render_spl_ids_stash) {
            $this->render_spl_ids = true;
        }
        return '<dl>' . $header . $children . '</dl>';
    }
    public function render_header_wrapper(Context_Interface $c, bool $has_children, string $contents): string
    {
        $out = '<dt';
        if ($has_children) {
            $out .= ' class="kint-parent';
            if ($this->expand) {
                $out .= ' kint-show';
            }
            $out .= '"';
        }
        $out .= '>';
        if (self::$access_paths && $c->get_depth() > 0 && null !== $ap = $c->get_access_path()) {
            $out .= '<span class="kint-access-path-trigger" title="Show access path"></span>';
        }
        if ($has_children) {
            if (0 === $c->get_depth()) {
                if (!$this->use_folder) {
                    $out .= '<span class="kint-folder-trigger" title="Move to folder"></span>';
                }
                $out .= '<span class="kint-search-trigger" title="Show search box"></span>';
                $out .= '<input type="text" class="kint-search" value="">';
            }
            $out .= '<nav></nav>';
        }
        $out .= $contents;
        if (!empty($ap)) {
            $out .= '<div class="access-path">' . $this->escape($ap) . '</div>';
        }
        return $out . '</dt>';
    }
    public function render_header(Abstract_Value $v): string
    {
        $c = $v->get_context();
        $output = '';
        if ($c instanceof Class_Declared_Context) {
            $output .= '<var>' . $c->get_modifiers() . '</var> ';
        }
        $output .= '<dfn>' . $this->escape($v->get_display_name()) . '</dfn> ';
        if ($c instanceof Property_Context && null !== $s = $c->get_hooks()) {
            $output .= '<var>' . $this->escape($s) . '</var> ';
        }
        if (null !== $s = $c->get_operator()) {
            $output .= $this->escape($s, 'ASCII') . ' ';
        }
        $s = $v->get_display_type();
        if (self::$escape_types) {
            $s = $this->escape($s);
        }
        if ($c->is_ref()) {
            $s = '&amp;' . $s;
        }
        $output .= '<var>' . $s . '</var>';
        if ($v instanceof Instance_Value && $this->should_render_object_ids()) {
            $output .= '#' . $v->get_spl_object_id();
        }
        $output .= ' ';
        if (null !== $s = $v->get_display_size()) {
            if (self::$escape_types) {
                $s = $this->escape($s);
            }
            $output .= '(' . $s . ') ';
        }
        if (null !== $s = $v->get_display_value()) {
            $s = (string) \preg_replace('/\s+/', ' ', $s);
            if (self::$strlen_max) {
                $s = Utils::truncate_string($s, self::$strlen_max);
            }
            $output .= $this->escape($s);
        }
        return \trim($output);
    }
    public function render_children(Abstract_Value $v): string
    {
        $contents = [];
        $tabs = [];
        foreach ($v->get_representations() as $rep) {
            $result = $this->render_tab($v, $rep);
            if (\strlen($result)) {
                $contents[] = $result;
                $tabs[] = $rep;
            }
        }
        if (empty($tabs)) {
            return '';
        }
        $output = '<dd>';
        if (1 === \count($tabs) && $tabs[0]->label_is_implicit()) {
            $output .= (string) \reset($contents);
        } else {
            $output .= '<ul class="kint-tabs">';
            foreach ($tabs as $i => $tab) {
                if (0 === $i) {
                    $output .= '<li class="kint-active-tab">';
                } else {
                    $output .= '<li>';
                }
                $output .= $this->escape($tab->get_label()) . '</li>';
            }
            $output .= '</ul><ul class="kint-tab-contents">';
            foreach ($contents as $i => $tab) {
                if (0 === $i) {
                    $output .= '<li class="kint-show">';
                } else {
                    $output .= '<li>';
                }
                $output .= $tab . '</li>';
            }
            $output .= '</ul>';
        }
        return $output . '</dd>';
    }
    public function pre_render(): string
    {
        $output = '';
        if ($this->should_pre_render()) {
            foreach (self::$pre_render_sources as $type => $values) {
                $contents = '';
                foreach ($values as $v) {
                    $contents .= \call_user_func($v, $this);
                }
                if (!\strlen($contents)) {
                    continue;
                }
                switch ($type) {
                    case 'script':
                        $output .= '<script class="kint-rich-script"';
                        if (null !== self::$js_nonce) {
                            $output .= ' nonce="' . \htmlspecialchars(self::$js_nonce) . '"';
                        }
                        $output .= '>' . $contents . '</script>';
                        break;
                    case 'style':
                        $output .= '<style class="kint-rich-style"';
                        if (null !== self::$css_nonce) {
                            $output .= ' nonce="' . \htmlspecialchars(self::$css_nonce) . '"';
                        }
                        $output .= '>' . $contents . '</style>';
                        break;
                    default:
                        $output .= $contents;
                }
            }
            // Don't pre-render on every dump
            if (!$this->force_pre_render) {
                self::$needs_pre_render = false;
            }
        }
        $output .= '<div class="kint-rich';
        if ($this->use_folder) {
            $output .= ' kint-file';
        }
        $output .= '">';
        return $output;
    }
    public function post_render(): string
    {
        if (!$this->show_trace) {
            return '</div>';
        }
        $output = '<footer';
        if ($this->expand) {
            $output .= ' class="kint-show"';
        }
        $output .= '>';
        if (!$this->use_folder) {
            $output .= '<span class="kint-folder-trigger" title="Move to folder">&mapstodown;</span>';
        }
        if (!empty($this->trace) && \count($this->trace) > 1) {
            $output .= '<nav></nav>';
        }
        $output .= $this->called_from();
        if (!empty($this->trace) && \count($this->trace) > 1) {
            $output .= '<ol>';
            foreach ($this->trace as $index => $step) {
                if (!$index) {
                    continue;
                }
                $output .= '<li>' . $this->ide_link($step['file'], $step['line']);
                // closing tag not required
                if (isset($step['function']) && !\in_array($step['function'], ['include', 'include_once', 'require', 'require_once'], true)) {
                    $output .= ' [';
                    $output .= $step['class'] ?? '';
                    $output .= $step['type'] ?? '';
                    $output .= $step['function'] . '()]';
                }
            }
            $output .= '</ol>';
        }
        $output .= '</footer></div>';
        return $output;
    }
    /**
     * @psalm-param Encoding $encoding
     */
    public function escape(string $string, $encoding = false): string
    {
        if (false === $encoding) {
            $encoding = Utils::detect_encoding($string);
        }
        $original_encoding = $encoding;
        if (false === $encoding || 'ASCII' === $encoding) {
            $encoding = 'UTF-8';
        }
        $string = \htmlspecialchars($string, ENT_NOQUOTES, $encoding);
        // this call converts all non-ASCII characters into numeirc htmlentities
        if (\function_exists('mb_encode_numericentity') && 'ASCII' !== $original_encoding) {
            $string = \mb_encode_numericentity($string, [0x80, 0xffff, 0, 0xffff], $encoding);
        }
        return $string;
    }
    public function ide_link(string $file, int $line): string
    {
        $path = $this->escape(Utils::shorten_path($file)) . ':' . $line;
        $ide_link = self::get_file_link($file, $line);
        if (null === $ide_link) {
            return $path;
        }
        return '<a href="' . $this->escape($ide_link) . '">' . $path . '</a>';
    }
    protected function called_from(): string
    {
        $output = '';
        if (isset($this->callee['file'])) {
            $output .= ' ' . $this->ide_link($this->callee['file'], $this->callee['line']);
        }
        if (isset($this->callee['function']) && (!empty($this->callee['class']) || !\in_array($this->callee['function'], ['include', 'include_once', 'require', 'require_once'], true))) {
            $output .= ' [';
            $output .= $this->callee['class'] ?? '';
            $output .= $this->callee['type'] ?? '';
            $output .= $this->callee['function'] . '()]';
        }
        if ('' !== $output) {
            $output = 'Called from' . $output;
        }
        if (null !== self::$timestamp) {
            $output .= ' ' . \date(self::$timestamp);
        }
        return $output;
    }
    protected function render_tab(Abstract_Value $v, Representation_Interface $rep): string
    {
        if ($plugin = $this->get_tab_plugin($rep)) {
            $output = $plugin->render_tab($rep, $v);
            if (null !== $output) {
                return $output;
            }
        }
        if ($rep instanceof Value_Representation) {
            return $this->render($rep->get_value());
        }
        if ($rep instanceof Container_Representation) {
            $output = '';
            foreach ($rep->get_contents() as $obj) {
                $output .= $this->render($obj);
            }
            return $output;
        }
        if ($rep instanceof String_Representation) {
            // If we're dealing with the content representation
            if ($v instanceof String_Value && $rep->get_value() === $v->get_value()) {
                // Only show the contents if:
                if (\preg_match('/(:?[\r\n\t\f\v]| {2})/', $rep->get_value())) {
                    // We have unrepresentable whitespace (Without whitespace preservation)
                    $show_contents = true;
                } elseif (self::$strlen_max && Utils::strlen($v->get_display_value()) > self::$strlen_max) {
                    // We had to truncate getDisplayValue
                    $show_contents = true;
                } else {
                    $show_contents = false;
                }
            } else {
                $show_contents = true;
            }
            if ($show_contents) {
                return '<pre>' . $this->escape($rep->get_value()) . "\n</pre>";
            }
        }
        return '';
    }
    protected function get_value_plugin(Abstract_Value $v): ?Value_Plugin_Interface
    {
        $hint = $v->get_hint();
        if (null === $hint || !isset(self::$value_plugins[$hint])) {
            return null;
        }
        $plugin = self::$value_plugins[$hint];
        if (!\is_a($plugin, Value_Plugin_Interface::class, true)) {
            return null;
        }
        if (!isset($this->plugin_objs[$plugin])) {
            $this->plugin_objs[$plugin] = new $plugin($this);
        }
        return $this->plugin_objs[$plugin];
    }
    protected function get_tab_plugin(Representation_Interface $r): ?Tab_Plugin_Interface
    {
        $hint = $r->get_hint();
        if (null === $hint || !isset(self::$tab_plugins[$hint])) {
            return null;
        }
        $plugin = self::$tab_plugins[$hint];
        if (!\is_a($plugin, Tab_Plugin_Interface::class, true)) {
            return null;
        }
        if (!isset($this->plugin_objs[$plugin])) {
            $this->plugin_objs[$plugin] = new $plugin($this);
        }
        return $this->plugin_objs[$plugin];
    }
}