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

use Kint\Renderer\Rich_Renderer;
use Kint\Utils;
use Kint\Value\Abstract_Value;
use Kint\Value\Array_Value;
use Kint\Value\Fixed_Width_Value;
use Kint\Value\Representation\Representation_Interface;
use Kint\Value\Representation\Table_Representation;
use Kint\Value\String_Value;
class Table_Plugin extends Abstract_Plugin implements Tab_Plugin_Interface
{
    public static bool $respect_str_length = true;
    public function render_tab(Representation_Interface $r, Abstract_Value $v): ?string
    {
        if (!$r instanceof Table_Representation) {
            return null;
        }
        $contents = $r->get_contents();
        $firstrow = \reset($contents);
        if (!$firstrow instanceof Array_Value) {
            return null;
        }
        $out = '<pre><table><thead><tr><th></th>';
        foreach ($firstrow->get_contents() as $field) {
            $out .= '<th>' . $this->renderer->escape($field->get_display_name()) . '</th>';
        }
        $out .= '</tr></thead><tbody>';
        foreach ($contents as $row) {
            if (!$row instanceof Array_Value) {
                return null;
            }
            $out .= '<tr><th>' . $this->renderer->escape($row->get_display_name()) . '</th>';
            foreach ($row->get_contents() as $field) {
                $ref = $field->get_context()->is_ref() ? '&amp;' : '';
                $type = $this->renderer->escape($field->get_display_type());
                $out .= '<td title="' . $ref . $type;
                if (null !== $size = $field->get_display_size()) {
                    $size = $this->renderer->escape($size);
                    $out .= ' (' . $size . ')';
                }
                $out .= '">';
                if ($field instanceof Fixed_Width_Value) {
                    if (null === $dv = $field->get_display_value()) {
                        $out .= '<var>' . $ref . 'null</var>';
                    } elseif ('boolean' === $field->get_type()) {
                        $out .= '<var>' . $ref . $dv . '</var>';
                    } else {
                        $out .= $dv;
                    }
                } elseif ($field instanceof String_Value) {
                    if (false !== $field->get_encoding()) {
                        $val = $field->get_value_utf8();
                        if (Rich_Renderer::$strlen_max && self::$respect_str_length) {
                            $val = Utils::truncate_string($val, Rich_Renderer::$strlen_max, 'UTF-8');
                        }
                        $out .= $this->renderer->escape($val);
                    } else {
                        $out .= '<var>' . $ref . $type . '</var>';
                    }
                } elseif ($field instanceof Array_Value) {
                    $out .= '<var>' . $ref . 'array</var> (' . $field->get_size() . ')';
                } else {
                    $out .= '<var>' . $ref . $type . '</var>';
                    if (null !== $size) {
                        $out .= ' (' . $size . ')';
                    }
                }
                if ($field->flags & Abstract_Value::FLAG_BLACKLIST) {
                    $out .= ' <var>Blacklisted</var>';
                } elseif ($field->flags & Abstract_Value::FLAG_RECURSION) {
                    $out .= ' <var>Recursion</var>';
                } elseif ($field->flags & Abstract_Value::FLAG_DEPTH_LIMIT) {
                    $out .= ' <var>Depth Limit</var>';
                }
                $out .= '</td>';
            }
            $out .= '</tr>';
        }
        $out .= '</tbody></table></pre>';
        return $out;
    }
}