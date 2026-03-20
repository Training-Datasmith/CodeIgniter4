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

use Kint\Parser;
use Kint\Parser\Plugin_Interface as ParserPluginInterface;
use Kint\Renderer\Text\Plugin_Interface;
use Kint\Utils;
use Kint\Value\Abstract_Value;
use Kint\Value\Array_Value;
use Kint\Value\Context\Array_Context;
use Kint\Value\Context\Class_Declared_Context;
use Kint\Value\Context\Property_Context;
use Kint\Value\Instance_Value;
use Kint\Value\String_Value;
/**
 * @psalm-import-type Encoding from StringValue
 */
class Text_Renderer extends Abstract_Renderer
{
    /**
     * TextRenderer plugins should implement PluginInterface.
     *
     * @psalm-var class-string<PluginInterface>[]
     */
    public static array $plugins = ['array_limit' => Text\Lock_Plugin::class, 'blacklist' => Text\Lock_Plugin::class, 'depth_limit' => Text\Lock_Plugin::class, 'splfileinfo' => Text\Spl_File_Info_Plugin::class, 'microtime' => Text\Microtime_Plugin::class, 'recursion' => Text\Lock_Plugin::class, 'trace' => Text\Trace_Plugin::class];
    /**
     * Parser plugins must be instanceof one of these or
     * it will be removed for performance reasons.
     *
     * @psalm-var class-string<ParserPluginInterface>[]
     */
    public static array $parser_plugin_whitelist = [Parser\Array_Limit_Plugin::class, Parser\Array_Object_Plugin::class, Parser\Blacklist_Plugin::class, Parser\Closure_Plugin::class, Parser\Date_Time_Plugin::class, Parser\Dom_Plugin::class, Parser\Enum_Plugin::class, Parser\Iterator_Plugin::class, Parser\Microtime_Plugin::class, Parser\Mysqli_Plugin::class, Parser\Simple_Xml_Element_Plugin::class, Parser\Spl_File_Info_Plugin::class, Parser\Stream_Plugin::class, Parser\Trace_Plugin::class];
    /**
     * The maximum length of a string before it is truncated.
     *
     * Falsey to disable
     */
    public static int $strlen_max = 0;
    /**
     * Timestamp to print in footer in date() format.
     */
    public static ?string $timestamp = null;
    /**
     * The default width of the terminal for headers.
     */
    public static int $default_width = 80;
    /**
     * Indentation width.
     */
    public static int $default_indent = 4;
    /**
     * Decorate the header and footer.
     */
    public static bool $decorations = true;
    public int $header_width = 80;
    public int $indent_width = 4;
    protected array $plugin_objs = [];
    public function __construct()
    {
        parent::__construct();
        $this->header_width = self::$default_width;
        $this->indent_width = self::$default_indent;
    }
    public function render(Abstract_Value $v): string
    {
        $render_spl_ids_stash = $this->render_spl_ids;
        if ($this->render_spl_ids && $v->flags & Abstract_Value::FLAG_GENERATED) {
            $this->render_spl_ids = false;
        }
        if ($plugin = $this->get_plugin($v)) {
            $output = $plugin->render($v);
            if (null !== $output && \strlen($output)) {
                if (!$this->render_spl_ids && $render_spl_ids_stash) {
                    $this->render_spl_ids = true;
                }
                return $output;
            }
        }
        $out = '';
        $c = $v->get_context();
        if (0 === $c->get_depth()) {
            $out .= $this->color_title($this->render_title($v)) . PHP_EOL;
        }
        $out .= $header = $this->render_header($v);
        $out .= $this->render_children($v);
        if (\strlen($header)) {
            $out .= PHP_EOL;
        }
        if (!$this->render_spl_ids && $render_spl_ids_stash) {
            $this->render_spl_ids = true;
        }
        return $out;
    }
    public function box_text(string $text, int $width): string
    {
        $out = '┌' . \str_repeat('─', $width - 2) . '┐' . PHP_EOL;
        if (\strlen($text)) {
            $text = Utils::truncate_string($text, $width - 4);
            $text = \str_pad($text, $width - 4);
            $out .= '│ ' . $this->escape($text) . ' │' . PHP_EOL;
        }
        $out .= '└' . \str_repeat('─', $width - 2) . '┘';
        return $out;
    }
    public function render_title(Abstract_Value $v): string
    {
        if (self::$decorations) {
            return $this->box_text($v->get_display_name(), $this->header_width);
        }
        return Utils::truncate_string($v->get_display_name(), $this->header_width);
    }
    public function render_header(Abstract_Value $v): string
    {
        $output = [];
        $c = $v->get_context();
        if ($c->get_depth() > 0) {
            if ($c instanceof Class_Declared_Context) {
                $output[] = $this->color_type($c->get_modifiers());
            }
            if ($c instanceof Array_Context) {
                $output[] = $this->escape(\var_export($c->get_name(), true));
            } else {
                $output[] = $this->escape((string) $c->get_name());
            }
            if ($c instanceof Property_Context && null !== $s = $c->get_hooks()) {
                $output[] = $this->color_type($this->escape($s));
            }
            if (null !== $s = $c->get_operator()) {
                $output[] = $this->escape($s);
            }
        }
        $s = $v->get_display_type();
        if ($c->is_ref()) {
            $s = '&' . $s;
        }
        $s = $this->color_type($this->escape($s));
        if ($v instanceof Instance_Value && $this->should_render_object_ids()) {
            $s .= '#' . $v->get_spl_object_id();
        }
        $output[] = $s;
        if (null !== $s = $v->get_display_size()) {
            $output[] = '(' . $this->escape($s) . ')';
        }
        if (null !== $s = $v->get_display_value()) {
            if (self::$strlen_max) {
                $s = Utils::truncate_string($s, self::$strlen_max);
            }
            $output[] = $this->color_value($this->escape($s));
        }
        return \str_repeat(' ', $c->get_depth() * $this->indent_width) . \implode(' ', $output);
    }
    public function render_children(Abstract_Value $v): string
    {
        $children = $v->get_display_children();
        if (!$children) {
            if ($v instanceof Array_Value) {
                return ' []';
            }
            return '';
        }
        if ($v instanceof Array_Value) {
            $output = ' [';
        } elseif ($v instanceof Instance_Value) {
            $output = ' (';
        } else {
            $output = '';
        }
        $output .= PHP_EOL;
        foreach ($children as $child) {
            $output .= $this->render($child);
        }
        $indent = \str_repeat(' ', $v->get_context()->get_depth() * $this->indent_width);
        if ($v instanceof Array_Value) {
            $output .= $indent . ']';
        } elseif ($v instanceof Instance_Value) {
            $output .= $indent . ')';
        }
        return $output;
    }
    public function color_value(string $string): string
    {
        return $string;
    }
    public function color_type(string $string): string
    {
        return $string;
    }
    public function color_title(string $string): string
    {
        return $string;
    }
    public function post_render(): string
    {
        if (self::$decorations) {
            $output = \str_repeat('═', $this->header_width);
        } else {
            $output = '';
        }
        if (!$this->show_trace) {
            return $this->color_title($output);
        }
        if ($output) {
            $output .= PHP_EOL;
        }
        return $this->color_title($output . $this->called_from() . PHP_EOL);
    }
    public function filter_parser_plugins(array $plugins): array
    {
        $return = [];
        foreach ($plugins as $plugin) {
            foreach (self::$parser_plugin_whitelist as $whitelist) {
                if ($plugin instanceof $whitelist) {
                    $return[] = $plugin;
                    continue 2;
                }
            }
        }
        return $return;
    }
    public function ide_link(string $file, int $line): string
    {
        return $this->escape(Utils::shorten_path($file)) . ':' . $line;
    }
    /**
     * @psalm-param Encoding $encoding
     */
    public function escape(string $string, $encoding = false): string
    {
        return $string;
    }
    protected function called_from(): string
    {
        $output = '';
        if (isset($this->callee['file'])) {
            $output .= 'Called from ' . $this->ide_link($this->callee['file'], $this->callee['line']);
        }
        if (isset($this->callee['function']) && (!empty($this->callee['class']) || !\in_array($this->callee['function'], ['include', 'include_once', 'require', 'require_once'], true))) {
            $output .= ' [';
            $output .= $this->callee['class'] ?? '';
            $output .= $this->callee['type'] ?? '';
            $output .= $this->callee['function'] . '()]';
        }
        if (null !== self::$timestamp) {
            if (\strlen($output)) {
                $output .= ' ';
            }
            $output .= \date(self::$timestamp);
        }
        return $output;
    }
    protected function get_plugin(Abstract_Value $v): ?Plugin_Interface
    {
        $hint = $v->get_hint();
        if (null === $hint || !isset(self::$plugins[$hint])) {
            return null;
        }
        $plugin = self::$plugins[$hint];
        if (!\is_a($plugin, Plugin_Interface::class, true)) {
            return null;
        }
        if (!isset($this->plugin_objs[$plugin])) {
            $this->plugin_objs[$plugin] = new $plugin($this);
        }
        return $this->plugin_objs[$plugin];
    }
}