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

use Json_Exception;
use Kint\Value\Abstract_Value;
use Kint\Value\Array_Value;
use Kint\Value\Context\Base_Context;
use Kint\Value\Representation\Container_Representation;
use Kint\Value\Representation\Value_Representation;
class Json_Plugin extends Abstract_Plugin implements Plugin_Complete_Interface
{
    public function get_types(): array
    {
        return ['string'];
    }
    public function get_triggers(): int
    {
        return Parser::TRIGGER_SUCCESS;
    }
    public function parse_complete(&$var, Abstract_Value $v, int $trigger): Abstract_Value
    {
        if (!isset($var[0]) || '{' !== $var[0] && '[' !== $var[0]) {
            return $v;
        }
        try {
            $json = \json_decode($var, true, 512, JSON_THROW_ON_ERROR);
        } catch (Json_Exception $e) {
            return $v;
        }
        $json = (array) $json;
        $c = $v->get_context();
        $base = new Base_Context('JSON Decode');
        $base->depth = $c->get_depth();
        if (null !== $ap = $c->get_access_path()) {
            $base->access_path = 'json_decode(' . $ap . ', true)';
        }
        $json = $this->get_parser()->parse($json, $base);
        if ($json instanceof Array_Value && ~$json->flags & Abstract_Value::FLAG_DEPTH_LIMIT && $contents = $json->get_contents()) {
            foreach ($contents as $value) {
                $value->flags |= Abstract_Value::FLAG_GENERATED;
            }
            $v->add_representation(new Container_Representation('Json', $contents), 0);
        } else {
            $json->flags |= Abstract_Value::FLAG_GENERATED;
            $v->add_representation(new Value_Representation('Json', $json), 0);
        }
        return $v;
    }
}