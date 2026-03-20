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

use Kint\Utils;
use Kint\Value\Abstract_Value;
use Kint\Value\Method_Value;
use Kint\Value\Representation\Callable_Definition_Representation;
use Kint\Value\Representation\Representation_Interface;
class Callable_Definition_Plugin extends Abstract_Plugin implements Tab_Plugin_Interface
{
    public function render_tab(Representation_Interface $r, Abstract_Value $v): ?string
    {
        if (!$r instanceof Callable_Definition_Representation) {
            return null;
        }
        $docstring = [];
        if ($v instanceof Method_Value) {
            $c = $v->get_context();
            if ($c->inherited) {
                $docstring[] = 'Inherited from ' . $this->renderer->escape($c->owner_class);
            }
        }
        $docstring[] = 'Defined in ' . $this->renderer->escape(Utils::shorten_path($r->get_file_name())) . ':' . $r->get_line();
        $docstring = '<small>' . \implode("\n", $docstring) . '</small>';
        if (null !== $trimmed = $r->get_docstring_trimmed()) {
            $docstring = $this->renderer->escape($trimmed) . "\n\n" . $docstring;
        }
        return '<pre>' . $docstring . '</pre>';
    }
}