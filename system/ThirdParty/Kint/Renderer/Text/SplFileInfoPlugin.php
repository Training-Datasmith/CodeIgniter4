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
use Kint\Value\Representation\Spl_File_Info_Representation;
class Spl_File_Info_Plugin extends Abstract_Plugin
{
    public function render(Abstract_Value $v): ?string
    {
        $r = $v->get_representation('splfileinfo');
        if (!$r instanceof Spl_File_Info_Representation) {
            return null;
        }
        $out = '';
        $c = $v->get_context();
        if (0 === $c->get_depth()) {
            $out .= $this->renderer->color_title($this->renderer->render_title($v)) . PHP_EOL;
        }
        $out .= $this->renderer->render_header($v);
        if (null !== $v->get_display_value()) {
            $out .= ' =>';
        }
        $out .= ' ' . $this->renderer->color_value($this->renderer->escape($r->get_value())) . PHP_EOL;
        return $out;
    }
}