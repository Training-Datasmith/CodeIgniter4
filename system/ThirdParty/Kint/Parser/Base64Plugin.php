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

use Kint\Value\Abstract_Value;
use Kint\Value\Context\Base_Context;
use Kint\Value\Representation\Value_Representation;
use Kint\Value\String_Value;
class Base64Plugin extends Abstract_Plugin implements Plugin_Complete_Interface
{
    /**
     * The minimum length before a string will be considered for base64 decoding.
     */
    public static int $min_length_hard = 16;
    /**
     * The minimum length before the base64 decoding will take precedence.
     */
    public static int $min_length_soft = 50;
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
        if (\strlen($var) < self::$min_length_hard || \strlen($var) % 4) {
            return $v;
        }
        if (\preg_match('/^[A-Fa-f0-9]+$/', $var)) {
            return $v;
        }
        if (!\preg_match('/^[A-Za-z0-9+\/=]+$/', $var)) {
            return $v;
        }
        $data = \base64_decode($var, true);
        if (false === $data) {
            return $v;
        }
        $c = $v->get_context();
        $base = new Base_Context('base64_decode(' . $c->get_name() . ')');
        $base->depth = $c->get_depth() + 1;
        if (null !== $ap = $c->get_access_path()) {
            $base->access_path = 'base64_decode(' . $ap . ')';
        }
        $data = $this->get_parser()->parse($data, $base);
        $data->flags |= Abstract_Value::FLAG_GENERATED;
        if (!$data instanceof String_Value || false === $data->get_encoding()) {
            return $v;
        }
        $r = new Value_Representation('Base64', $data);
        if (\strlen($var) > self::$min_length_soft) {
            $v->add_representation($r, 0);
        } else {
            $v->add_representation($r);
        }
        return $v;
    }
}