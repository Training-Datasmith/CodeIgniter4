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
namespace Kint\Renderer\Text;

use Kint\Value\Abstract_Value;
use Kint\Value\Method_Value;
use Kint\Value\Representation\Source_Representation;
use Kint\Value\Trace_Frame_Value;
use Kint\Value\Trace_Value;
class Trace_Plugin extends Abstract_Plugin
{
    public function render(Abstract_Value $v): ?string
    {
        if (!$v instanceof Trace_Value) {
            return null;
        }
        $c = $v->get_context();
        $out = '';
        if (0 === $c->get_depth()) {
            $out .= $this->renderer->color_title($this->renderer->render_title($v)) . PHP_EOL;
        }
        $out .= $this->renderer->render_header($v) . ':' . PHP_EOL;
        $indent = \str_repeat(' ', ($c->get_depth() + 1) * $this->renderer->indent_width);
        $i = 1;
        foreach ($v->get_contents() as $frame) {
            if (!$frame instanceof Trace_Frame_Value) {
                continue;
            }
            $framedesc = $indent . \str_pad($i . ': ', 4, ' ');
            if (null !== ($file = $frame->get_file()) && null !== $line = $frame->get_line()) {
                $framedesc .= $this->renderer->ide_link($file, $line) . PHP_EOL;
            } else {
                $framedesc .= 'PHP internal call' . PHP_EOL;
            }
            if ($callable = $frame->get_callable()) {
                $framedesc .= $indent . '    ';
                if ($callable instanceof Method_Value) {
                    $framedesc .= $this->renderer->escape($callable->get_context()->owner_class . $callable->get_context()->get_operator());
                }
                $framedesc .= $this->renderer->escape($callable->get_display_name());
            }
            $out .= $this->renderer->color_type($framedesc) . PHP_EOL . PHP_EOL;
            $source = $frame->get_representation('source');
            if ($source instanceof Source_Representation) {
                $line_wanted = $source->get_line();
                $source = $source->get_source_lines();
                // Trim empty lines from the start and end of the source
                foreach ($source as $linenum => $line) {
                    if (\trim($line) || $linenum === $line_wanted) {
                        break;
                    }
                    unset($source[$linenum]);
                }
                foreach (\array_reverse($source, true) as $linenum => $line) {
                    if (\trim($line) || $linenum === $line_wanted) {
                        break;
                    }
                    unset($source[$linenum]);
                }
                foreach ($source as $lineno => $line) {
                    if ($lineno === $line_wanted) {
                        $out .= $indent . $this->renderer->color_value($this->renderer->escape($line)) . PHP_EOL;
                    } else {
                        $out .= $indent . $this->renderer->escape($line) . PHP_EOL;
                    }
                }
            }
            ++$i;
        }
        return $out;
    }
}