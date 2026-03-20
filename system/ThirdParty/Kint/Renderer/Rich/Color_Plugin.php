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
use Kint\Value\Representation\Color_Representation;
use Kint\Value\Representation\Representation_Interface;
class Color_Plugin extends Abstract_Plugin implements Tab_Plugin_Interface, Value_Plugin_Interface
{
    public function render_value(Abstract_Value $v): ?string
    {
        $r = $v->get_representation('color');
        if (!$r instanceof Color_Representation) {
            return null;
        }
        $children = $this->renderer->render_children($v);
        $header = $this->renderer->render_header($v);
        $header .= '<div class="kint-color-preview"><div style="background:';
        $header .= $r->get_color(Color_Representation::COLOR_RGBA);
        $header .= '"></div></div>';
        $header = $this->renderer->render_header_wrapper($v->get_context(), (bool) \strlen($children), $header);
        return '<dl>' . $header . $children . '</dl>';
    }
    public function render_tab(Representation_Interface $r, Abstract_Value $v): ?string
    {
        if (!$r instanceof Color_Representation) {
            return null;
        }
        $out = '';
        if ($color = $r->get_color(Color_Representation::COLOR_NAME)) {
            $out .= '<dfn>' . $color . "</dfn>\n";
        }
        if ($color = $r->get_color(Color_Representation::COLOR_HEX_3)) {
            $out .= '<dfn>' . $color . "</dfn>\n";
        }
        if ($color = $r->get_color(Color_Representation::COLOR_HEX_6)) {
            $out .= '<dfn>' . $color . "</dfn>\n";
        }
        if ($r->has_alpha()) {
            if ($color = $r->get_color(Color_Representation::COLOR_HEX_4)) {
                $out .= '<dfn>' . $color . "</dfn>\n";
            }
            if ($color = $r->get_color(Color_Representation::COLOR_HEX_8)) {
                $out .= '<dfn>' . $color . "</dfn>\n";
            }
            if ($color = $r->get_color(Color_Representation::COLOR_RGBA)) {
                $out .= '<dfn>' . $color . "</dfn>\n";
            }
            if ($color = $r->get_color(Color_Representation::COLOR_HSLA)) {
                $out .= '<dfn>' . $color . "</dfn>\n";
            }
        } else {
            if ($color = $r->get_color(Color_Representation::COLOR_RGB)) {
                $out .= '<dfn>' . $color . "</dfn>\n";
            }
            if ($color = $r->get_color(Color_Representation::COLOR_HSL)) {
                $out .= '<dfn>' . $color . "</dfn>\n";
            }
        }
        if (!\strlen($out)) {
            return null;
        }
        return '<pre>' . $out . '</pre>';
    }
}