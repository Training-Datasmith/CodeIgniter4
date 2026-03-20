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
use Kint\Value\Abstract_Value;
use Kint\Value\Context\Class_Declared_Context;
use Kint\Value\Context\Property_Context;
use Kint\Value\Instance_Value;
abstract class Abstract_Plugin implements Plugin_Interface
{
    protected Rich_Renderer $renderer;
    public function __construct(Rich_Renderer $r)
    {
        $this->renderer = $r;
    }
    /**
     * @param string $content The replacement for the getValueShort contents
     */
    public function render_locked_header(Abstract_Value $v, string $content): string
    {
        $header = '<dt class="kint-parent kint-locked">';
        $c = $v->get_context();
        if (Rich_Renderer::$access_paths && $c->get_depth() > 0 && null !== $ap = $c->get_access_path()) {
            $header .= '<span class="kint-access-path-trigger" title="Show access path">&rlarr;</span>';
        }
        $header .= '<nav></nav>';
        if ($c instanceof Class_Declared_Context) {
            $header .= '<var>' . $c->get_modifiers() . '</var> ';
        }
        $header .= '<dfn>' . $this->renderer->escape($v->get_display_name()) . '</dfn> ';
        if ($c instanceof Property_Context && null !== $s = $c->get_hooks()) {
            $header .= '<var>' . $this->renderer->escape($s) . '</var> ';
        }
        if (null !== $s = $c->get_operator()) {
            $header .= $this->renderer->escape($s, 'ASCII') . ' ';
        }
        $s = $v->get_display_type();
        if (Rich_Renderer::$escape_types) {
            $s = $this->renderer->escape($s);
        }
        if ($c->is_ref()) {
            $s = '&amp;' . $s;
        }
        $header .= '<var>' . $s . '</var>';
        if ($v instanceof Instance_Value && $this->renderer->should_render_object_ids()) {
            $header .= '#' . $v->get_spl_object_id();
        }
        $header .= ' ';
        if (null !== $s = $v->get_display_size()) {
            if (Rich_Renderer::$escape_types) {
                $s = $this->renderer->escape($s);
            }
            $header .= '(' . $s . ') ';
        }
        $header .= $content;
        if (!empty($ap)) {
            $header .= '<div class="access-path">' . $this->renderer->escape($ap) . '</div>';
        }
        return $header . '</dt>';
    }
}