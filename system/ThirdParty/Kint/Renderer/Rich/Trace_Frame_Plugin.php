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
namespace Kint\Renderer\Rich;

use Kint\Value\Abstract_Value;
use Kint\Value\Method_Value;
use Kint\Value\Trace_Frame_Value;
class Trace_Frame_Plugin extends Abstract_Plugin implements Value_Plugin_Interface
{
    public function render_value(Abstract_Value $v): ?string
    {
        if (!$v instanceof Trace_Frame_Value) {
            return null;
        }
        if (null !== ($file = $v->get_file()) && null !== $line = $v->get_line()) {
            $header = '<var>' . $this->renderer->ide_link($file, $line) . '</var> ';
        } else {
            $header = '<var>PHP internal call</var> ';
        }
        if ($callable = $v->get_callable()) {
            if ($callable instanceof Method_Value) {
                $function = $callable->get_fully_qualified_display_name();
            } else {
                $function = $callable->get_display_name();
            }
            $function = $this->renderer->escape($function);
            if (null !== $url = $callable->get_php_doc_url()) {
                $function = '<a href="' . $url . '" target=_blank>' . $function . '</a>';
            }
            $header .= $function;
        }
        $children = $this->renderer->render_children($v);
        $header = $this->renderer->render_header_wrapper($v->get_context(), (bool) \strlen($children), $header);
        return '<dl>' . $header . $children . '</dl>';
    }
}