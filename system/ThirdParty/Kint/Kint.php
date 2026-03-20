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
namespace Kint;

use InvalidArgumentException;
use Kint\Parser\Constructable_Plugin_Interface;
use Kint\Parser\Parser;
use Kint\Parser\Plugin_Interface;
use Kint\Renderer\Constructable_Renderer_Interface;
use Kint\Renderer\Renderer_Interface;
use Kint\Renderer\Text_Renderer;
use Kint\Value\Context\Base_Context;
use Kint\Value\Context\Context_Interface;
use Kint\Value\Trace_Frame_Value;
use Kint\Value\Uninitialized_Value;
/**
 * @psalm-consistent-constructor
 * Psalm bug #8523
 *
 * @psalm-import-type CallParameter from CallFinder
 * @psalm-import-type TraceFrame from TraceFrameValue
 *
 * @psalm-type KintMode = array-key|bool
 * @psalm-type KintCallInfo = array{
 *   params: ?list<CallParameter>,
 *   modifiers: array,
 *   callee: ?callable,
 *   caller: ?callable,
 *   trace: TraceFrame[],
 * }
 *
 * @psalm-api
 */
class Kint implements Facade_Interface
{
    public const MODE_RICH = 'r';
    public const MODE_TEXT = 't';
    public const MODE_CLI = 'c';
    public const MODE_PLAIN = 'p';
    /**
     * @var mixed Kint mode
     *
     * false: Disabled
     * true: Enabled, default mode selection
     * other: Manual mode selection
     *
     * @psalm-var KintMode
     */
    public static $enabled_mode = true;
    /**
     * Default mode.
     *
     * @psalm-var KintMode
     */
    public static $mode_default = self::MODE_RICH;
    /**
     * Default mode in CLI with cli_detection on.
     *
     * @psalm-var KintMode
     */
    public static $mode_default_cli = self::MODE_CLI;
    /**
     * @var bool enable detection when Kint is command line.
     *
     * Formats output with whitespace only; does not HTML-escape it
     */
    public static bool $cli_detection = true;
    /**
     * @var bool Return output instead of echoing
     */
    public static bool $return = false;
    /**
     * @var int depth limit for array/object traversal. 0 for no limit
     */
    public static int $depth_limit = 7;
    /**
     * @var bool expand all trees by default for rich view
     */
    public static bool $expanded = false;
    /**
     * @var bool whether to display where kint was called from
     */
    public static bool $display_called_from = true;
    /**
     * @var array Kint aliases. Add debug functions in Kint wrappers here to fix modifiers and backtraces
     */
    public static array $aliases = [[self::class, 'dump'], [self::class, 'trace'], [self::class, 'dumpAll']];
    /**
     * @psalm-var array<RendererInterface|class-string<ConstructableRendererInterface>>
     *
     * Array of modes to renderer class names
     */
    public static array $renderers = [self::MODE_RICH => Renderer\Rich_Renderer::class, self::MODE_PLAIN => Renderer\Plain_Renderer::class, self::MODE_TEXT => Text_Renderer::class, self::MODE_CLI => Renderer\Cli_Renderer::class];
    /**
     * @psalm-var array<PluginInterface|class-string<ConstructablePluginInterface>>
     */
    public static array $plugins = [
        \Kint\Parser\Array_Limit_Plugin::class,
        \Kint\Parser\Array_Object_Plugin::class,
        \Kint\Parser\Base64Plugin::class,
        \Kint\Parser\Binary_Plugin::class,
        \Kint\Parser\Blacklist_Plugin::class,
        \Kint\Parser\Class_Hooks_Plugin::class,
        \Kint\Parser\Class_Methods_Plugin::class,
        \Kint\Parser\Class_Statics_Plugin::class,
        \Kint\Parser\Class_Strings_Plugin::class,
        \Kint\Parser\Closure_Plugin::class,
        \Kint\Parser\Color_Plugin::class,
        \Kint\Parser\Date_Time_Plugin::class,
        \Kint\Parser\Dom_Plugin::class,
        \Kint\Parser\Enum_Plugin::class,
        \Kint\Parser\Fs_Path_Plugin::class,
        \Kint\Parser\Html_Plugin::class,
        \Kint\Parser\Iterator_Plugin::class,
        \Kint\Parser\Json_Plugin::class,
        \Kint\Parser\Microtime_Plugin::class,
        \Kint\Parser\Mysqli_Plugin::class,
        // \Kint\Parser\SerializePlugin::class,
        \Kint\Parser\Simple_Xml_Element_Plugin::class,
        \Kint\Parser\Spl_File_Info_Plugin::class,
        \Kint\Parser\Stream_Plugin::class,
        \Kint\Parser\Table_Plugin::class,
        \Kint\Parser\Throwable_Plugin::class,
        \Kint\Parser\Timestamp_Plugin::class,
        \Kint\Parser\To_String_Plugin::class,
        \Kint\Parser\Trace_Plugin::class,
        \Kint\Parser\Xml_Plugin::class,
    ];
    protected Parser $parser;
    protected Renderer_Interface $renderer;
    public function __construct(Parser $p, Renderer_Interface $r)
    {
        $this->parser = $p;
        $this->renderer = $r;
    }
    public function set_parser(Parser $p): void
    {
        $this->parser = $p;
    }
    public function get_parser(): Parser
    {
        return $this->parser;
    }
    public function set_renderer(Renderer_Interface $r): void
    {
        $this->renderer = $r;
    }
    public function get_renderer(): Renderer_Interface
    {
        return $this->renderer;
    }
    public function set_states_from_statics(array $statics): void
    {
        $this->renderer->set_statics($statics);
        $this->parser->set_depth_limit($statics['depth_limit'] ?? 0);
        $this->parser->clear_plugins();
        if (!isset($statics['plugins'])) {
            return;
        }
        $plugins = [];
        foreach ($statics['plugins'] as $plugin) {
            if ($plugin instanceof Plugin_Interface) {
                $plugins[] = $plugin;
            } elseif (\is_string($plugin) && \is_a($plugin, Constructable_Plugin_Interface::class, true)) {
                $plugins[] = new $plugin($this->parser);
            }
        }
        $plugins = $this->renderer->filter_parser_plugins($plugins);
        foreach ($plugins as $plugin) {
            try {
                $this->parser->add_plugin($plugin);
            } catch (InvalidArgumentException $e) {
                \trigger_error('Plugin ' . Utils::error_sanitize_string(\get_class($plugin)) . ' could not be added to a Kint parser: ' . Utils::error_sanitize_string($e->get_message()), E_USER_WARNING);
            }
        }
    }
    public function set_states_from_call_info(array $info): void
    {
        $this->renderer->set_call_info($info);
        if (isset($info['modifiers']) && \is_array($info['modifiers']) && \in_array('+', $info['modifiers'], true)) {
            $this->parser->set_depth_limit(0);
        }
        $this->parser->set_caller_class($info['caller']['class'] ?? null);
    }
    public function dump_all(array $vars, array $base): string
    {
        if (\array_keys($vars) !== \array_keys($base)) {
            throw new InvalidArgumentException('Kint::dumpAll requires arrays of identical size and keys as arguments');
        }
        if ([] === $vars) {
            return $this->dump_nothing();
        }
        $output = $this->renderer->pre_render();
        foreach ($vars as $key => $_) {
            if (!$base[$key] instanceof Context_Interface) {
                throw new InvalidArgumentException('Kint::dumpAll requires all elements of the second argument to be ContextInterface instances');
            }
            $output .= $this->dump_var($vars[$key], $base[$key]);
        }
        $output .= $this->renderer->post_render();
        return $output;
    }
    protected function dump_nothing(): string
    {
        $output = $this->renderer->pre_render();
        $output .= $this->renderer->render(new Uninitialized_Value(new Base_Context('No argument')));
        $output .= $this->renderer->post_render();
        return $output;
    }
    /**
     * Dumps and renders a var.
     *
     * @param mixed &$var Data to dump
     */
    protected function dump_var(&$var, Context_Interface $c): string
    {
        return $this->renderer->render($this->parser->parse($var, $c));
    }
    /**
     * Gets all static settings at once.
     *
     * @return array Current static settings
     */
    public static function get_statics(): array
    {
        return ['aliases' => static::$aliases, 'cli_detection' => static::$cli_detection, 'depth_limit' => static::$depth_limit, 'display_called_from' => static::$display_called_from, 'enabled_mode' => static::$enabled_mode, 'expanded' => static::$expanded, 'mode_default' => static::$mode_default, 'mode_default_cli' => static::$mode_default_cli, 'plugins' => static::$plugins, 'renderers' => static::$renderers, 'return' => static::$return];
    }
    /**
     * Creates a Kint instance based on static settings.
     *
     * @param array $statics array of statics as returned by getStatics
     */
    public static function create_from_statics(array $statics): ?Facade_Interface
    {
        $mode = false;
        if (isset($statics['enabled_mode'])) {
            $mode = $statics['enabled_mode'];
            if (true === $mode && isset($statics['mode_default'])) {
                $mode = $statics['mode_default'];
                if (PHP_SAPI === 'cli' && !empty($statics['cli_detection']) && isset($statics['mode_default_cli'])) {
                    $mode = $statics['mode_default_cli'];
                }
            }
        }
        if (false === $mode) {
            return null;
        }
        $renderer = null;
        if (isset($statics['renderers'][$mode])) {
            if ($statics['renderers'][$mode] instanceof Renderer_Interface) {
                $renderer = $statics['renderers'][$mode];
            }
            if (\is_a($statics['renderers'][$mode], Constructable_Renderer_Interface::class, true)) {
                $renderer = new $statics['renderers'][$mode]();
            }
        }
        $renderer ??= new Text_Renderer();
        return new static(new Parser(), $renderer);
    }
    /**
     * Creates base contexts given parameter info.
     *
     * @psalm-param list<CallParameter> $params
     *
     * @return BaseContext[] Base contexts for the arguments
     */
    public static function get_bases_from_param_info(array $params, int $argc): array
    {
        $bases = [];
        for ($i = 0; $i < $argc; ++$i) {
            $param = $params[$i] ?? null;
            if (!empty($param['literal'])) {
                $name = 'literal';
            } else {
                $name = $param['name'] ?? '$' . $i;
            }
            if (isset($param['path'])) {
                $access_path = $param['path'];
                if ($param['expression']) {
                    $access_path = '(' . $access_path . ')';
                } elseif ($param['new_without_parens']) {
                    $access_path .= '()';
                }
            } else {
                $access_path = '$' . $i;
            }
            $base = new Base_Context($name);
            $base->access_path = $access_path;
            $bases[] = $base;
        }
        return $bases;
    }
    /**
     * Gets call info from the backtrace, alias, and argument count.
     *
     * Aliases must be normalized beforehand (Utils::normalizeAliases)
     *
     * @param array   $aliases Call aliases as found in Kint::$aliases
     * @param array[] $trace   Backtrace
     * @param array   $args    Arguments
     *
     * @psalm-param list<non-empty-array> $trace
     *
     * @return KintCallInfo Call info
     */
    public static function get_call_info(array $aliases, array $trace, array $args): array
    {
        $found = false;
        $callee = null;
        $caller = null;
        $mini_trace = [];
        foreach ($trace as $frame) {
            if (Utils::trace_frame_is_listed($frame, $aliases)) {
                $found = true;
                $mini_trace = [];
            }
            if (!Utils::trace_frame_is_listed($frame, ['spl_autoload_call'])) {
                $mini_trace[] = $frame;
            }
        }
        if ($found) {
            $callee = \reset($mini_trace) ?: null;
            $caller = \next($mini_trace) ?: null;
        }
        foreach ($mini_trace as $index => $frame) {
            if (0 === $index && $callee === $frame || isset($frame['file'], $frame['line'])) {
                unset($frame['object'], $frame['args']);
                $mini_trace[$index] = $frame;
            } else {
                unset($mini_trace[$index]);
            }
        }
        $mini_trace = \array_values($mini_trace);
        $call = static::get_single_call($callee ?: [], $args);
        $ret = ['params' => null, 'modifiers' => [], 'callee' => $callee, 'caller' => $caller, 'trace' => $mini_trace];
        if (null !== $call) {
            $ret['params'] = $call['parameters'];
            $ret['modifiers'] = $call['modifiers'];
        }
        return $ret;
    }
    /**
     * Dumps a backtrace.
     *
     * Functionally equivalent to Kint::dump(1) or Kint::dump(debug_backtrace(true))
     *
     * @return int|string
     */
    public static function trace()
    {
        if (false === static::$enabled_mode) {
            return 0;
        }
        static::$aliases = Utils::normalize_aliases(static::$aliases);
        $call_info = static::get_call_info(static::$aliases, \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), []);
        $statics = static::get_statics();
        if (\in_array('~', $call_info['modifiers'], true)) {
            $statics['enabled_mode'] = static::MODE_TEXT;
        }
        $kintstance = static::create_from_statics($statics);
        if (!$kintstance) {
            return 0;
        }
        if (\in_array('-', $call_info['modifiers'], true)) {
            while (\ob_get_level()) {
                \ob_end_clean();
            }
        }
        $kintstance->set_states_from_statics($statics);
        $kintstance->set_states_from_call_info($call_info);
        $trimmed_trace = [];
        $trace = \debug_backtrace();
        foreach ($trace as $frame) {
            if (Utils::trace_frame_is_listed($frame, static::$aliases)) {
                $trimmed_trace = [];
            }
            $trimmed_trace[] = $frame;
        }
        \array_shift($trimmed_trace);
        $base = new Base_Context('Kint\Kint::trace()');
        $base->access_path = 'debug_backtrace()';
        $output = $kintstance->dump_all([$trimmed_trace], [$base]);
        if (static::$return || \in_array('@', $call_info['modifiers'], true)) {
            return $output;
        }
        echo $output;
        if (\in_array('-', $call_info['modifiers'], true)) {
            \flush();
            // @codeCoverageIgnore
        }
        return 0;
    }
    /**
     * Dumps some data.
     *
     * Functionally equivalent to Kint::dump(1) or Kint::dump(debug_backtrace())
     *
     * @psalm-param mixed ...$args
     *
     * @return int|string
     */
    public static function dump(...$args)
    {
        if (false === static::$enabled_mode) {
            return 0;
        }
        static::$aliases = Utils::normalize_aliases(static::$aliases);
        $call_info = static::get_call_info(static::$aliases, \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), $args);
        $statics = static::get_statics();
        if (\in_array('~', $call_info['modifiers'], true)) {
            $statics['enabled_mode'] = static::MODE_TEXT;
        }
        $kintstance = static::create_from_statics($statics);
        if (!$kintstance) {
            return 0;
        }
        if (\in_array('-', $call_info['modifiers'], true)) {
            while (\ob_get_level()) {
                \ob_end_clean();
            }
        }
        $kintstance->set_states_from_statics($statics);
        $kintstance->set_states_from_call_info($call_info);
        $bases = static::get_bases_from_param_info($call_info['params'] ?? [], \count($args));
        $output = $kintstance->dump_all(\array_values($args), $bases);
        if (static::$return || \in_array('@', $call_info['modifiers'], true)) {
            return $output;
        }
        echo $output;
        if (\in_array('-', $call_info['modifiers'], true)) {
            \flush();
            // @codeCoverageIgnore
        }
        return 0;
    }
    /**
     * Returns specific function call info from a stack trace frame, or null if no match could be found.
     *
     * @param array $frame The stack trace frame in question
     * @param array $args  The arguments
     *
     * @return ?array params and modifiers, or null if a specific call could not be determined
     */
    protected static function get_single_call(array $frame, array $args): ?array
    {
        if (!isset($frame['file'], $frame['line'], $frame['function']) || !\is_readable($frame['file']) || false === $source = \file_get_contents($frame['file'])) {
            return null;
        }
        if (empty($frame['class'])) {
            $callfunc = $frame['function'];
        } else {
            $callfunc = [$frame['class'], $frame['function']];
        }
        $calls = Call_Finder::get_function_calls($source, $frame['line'], $callfunc);
        $argc = \count($args);
        $return = null;
        foreach ($calls as $call) {
            $is_unpack = false;
            // Handle argument unpacking as a last resort
            foreach ($call['parameters'] as $i => &$param) {
                if (0 === \strpos($param['name'], '...')) {
                    $is_unpack = true;
                    // If we're on the last param
                    if ($i < $argc && $i === \count($call['parameters']) - 1) {
                        unset($call['parameters'][$i]);
                        if (Utils::is_assoc($args)) {
                            // Associated unpacked arrays can be accessed by key
                            $keys = \array_slice(\array_keys($args), $i);
                            foreach ($keys as $key) {
                                $call['parameters'][] = ['name' => (string) \substr($param['name'], 3) . '[' . \var_export($key, true) . ']', 'path' => (string) \substr($param['path'], 3) . '[' . \var_export($key, true) . ']', 'expression' => false, 'literal' => false, 'new_without_parens' => false];
                            }
                        } else {
                            // Numeric unpacked arrays have their order blown away like a pass
                            // through array_values so we can't access them directly at all
                            for ($j = 0; $j + $i < $argc; ++$j) {
                                $call['parameters'][] = ['name' => 'array_values(' . (string) \substr($param['name'], 3) . ')[' . $j . ']', 'path' => 'array_values(' . (string) \substr($param['path'], 3) . ')[' . $j . ']', 'expression' => false, 'literal' => false, 'new_without_parens' => false];
                            }
                        }
                        $call['parameters'] = \array_values($call['parameters']);
                    } else {
                        $call['parameters'] = \array_slice($call['parameters'], 0, $i);
                    }
                    break;
                }
                if ($i >= $argc) {
                    continue 2;
                }
            }
            if ($is_unpack || \count($call['parameters']) === $argc) {
                if (null === $return) {
                    $return = $call;
                } else {
                    // If we have multiple calls on the same line with the same amount of arguments,
                    // we can't be sure which it is so just return null and let them figure it out
                    return null;
                }
            }
        }
        return $return;
    }
}