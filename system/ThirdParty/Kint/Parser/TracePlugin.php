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
namespace Kint\Parser;

use Kint\Utils;
use Kint\Value\Abstract_Value;
use Kint\Value\Array_Value;
use Kint\Value\Context\Array_Context;
use Kint\Value\Representation\Container_Representation;
use Kint\Value\Representation\Source_Representation;
use Kint\Value\Representation\Value_Representation;
use Kint\Value\Trace_Frame_Value;
use Kint\Value\Trace_Value;
use RuntimeException;
/**
 * @psalm-import-type TraceFrame from TraceFrameValue
 */
class Trace_Plugin extends Abstract_Plugin implements Plugin_Complete_Interface
{
    public static array $blacklist = ['spl_autoload_call'];
    public static array $path_blacklist = [];
    public function get_types(): array
    {
        return ['array'];
    }
    public function get_triggers(): int
    {
        return Parser::TRIGGER_SUCCESS;
    }
    public function parse_complete(&$var, Abstract_Value $v, int $trigger): Abstract_Value
    {
        if (!$v instanceof Array_Value) {
            return $v;
        }
        // Shallow copy so we don't have to worry about touching var
        $trace = $var;
        if (!Utils::is_trace($trace)) {
            return $v;
        }
        $pdepth = $this->get_parser()->get_depth_limit();
        $c = $v->get_context();
        // We need at least 2 levels in order to get $trace[n]['args']
        if ($pdepth && $c->get_depth() + 2 >= $pdepth) {
            return $v;
        }
        $contents = $v->get_contents();
        self::$blacklist = Utils::normalize_aliases(self::$blacklist);
        $path_blacklist = self::normalize_paths(self::$path_blacklist);
        $frames = [];
        foreach ($contents as $frame) {
            if (!$frame instanceof Array_Value || !$frame->get_context() instanceof Array_Context) {
                continue;
            }
            $index = $frame->get_context()->get_name();
            if (!isset($trace[$index]['file']) || Utils::trace_frame_is_listed($trace[$index], self::$blacklist)) {
                continue;
            }
            if (false !== $realfile = \realpath($trace[$index]['file'])) {
                foreach ($path_blacklist as $path) {
                    if (0 === \strpos($realfile, $path)) {
                        continue 2;
                    }
                }
            }
            $frame = new Trace_Frame_Value($frame, $trace[$index]);
            if (null !== ($file = $frame->get_file()) && null !== $line = $frame->get_line()) {
                try {
                    $frame->add_representation(new Source_Representation($file, $line));
                } catch (RuntimeException $e) {
                }
            }
            if ($args = $frame->get_args()) {
                $frame->add_representation(new Container_Representation('Arguments', $args));
            }
            if ($obj = $frame->get_object()) {
                $frame->add_representation(new Value_Representation('Callee object [' . $obj->get_class_name() . ']', $obj, 'callee_object'));
            }
            $frames[$index] = $frame;
        }
        $traceobj = new Trace_Value($c, \count($frames), $frames);
        if ($frames) {
            $traceobj->add_representation(new Container_Representation('Contents', $frames, null, true));
        }
        return $traceobj;
    }
    protected static function normalize_paths(array $paths): array
    {
        $normalized = [];
        foreach ($paths as $path) {
            $realpath = \realpath($path);
            if (false !== $realpath && \is_dir($realpath)) {
                $realpath .= DIRECTORY_SEPARATOR;
            }
            $normalized[] = $realpath;
        }
        return $normalized;
    }
}