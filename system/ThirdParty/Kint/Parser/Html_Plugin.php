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

use Dom\Html_Document;
use Dom_Exception;
use Kint\Value\Abstract_Value;
use Kint\Value\Context\Base_Context;
use Kint\Value\Representation\Container_Representation;
use Kint\Value\Representation\Value_Representation;
class Html_Plugin extends Abstract_Plugin implements Plugin_Complete_Interface
{
    public function get_types(): array
    {
        return ['string'];
    }
    public function get_triggers(): int
    {
        if (!KINT_PHP84) {
            return Parser::TRIGGER_NONE;
            // @codeCoverageIgnore
        }
        return Parser::TRIGGER_SUCCESS;
    }
    public function parse_complete(&$var, Abstract_Value $v, int $trigger): Abstract_Value
    {
        if ('<!doctype html>' !== \strtolower((string) \substr($var, 0, 15))) {
            return $v;
        }
        try {
            $html = Html_Document::create_from_string($var, LIBXML_NOERROR);
        } catch (Dom_Exception $e) {
            // @codeCoverageIgnore
            return $v;
            // @codeCoverageIgnore
        }
        $c = $v->get_context();
        $base = new Base_Context('childNodes');
        $base->depth = $c->get_depth();
        if (null !== $ap = $c->get_access_path()) {
            $base->access_path = '\Dom\HTMLDocument::createFromString(' . $ap . ')->childNodes';
        }
        $out = $this->get_parser()->parse($html->child_nodes, $base);
        $iter = $out->get_representation('iterator');
        if ($out->flags & Abstract_Value::FLAG_DEPTH_LIMIT) {
            $out->flags |= Abstract_Value::FLAG_GENERATED;
            $v->add_representation(new Value_Representation('HTML', $out), 0);
        } elseif ($iter instanceof Container_Representation) {
            $v->add_representation(new Container_Representation('HTML', $iter->get_contents()), 0);
        }
        return $v;
    }
}