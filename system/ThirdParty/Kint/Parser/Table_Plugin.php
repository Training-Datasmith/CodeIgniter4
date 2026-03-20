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
use Kint\Value\Array_Value;
use Kint\Value\Representation\Table_Representation;
// Note: Interaction with ArrayLimitPlugin:
// Any array limited children will be shown in tables identically to
// non-array-limited children since the table only shows that it is an array
// and it's size anyway. Because ArrayLimitPlugin halts the parse on finding
// a limit all other plugins including this one are stopped, so you cannot get
// a tabular representation of an array that is longer than the limit.
class Table_Plugin extends Abstract_Plugin implements Plugin_Complete_Interface
{
    public static int $max_width = 300;
    public static int $min_width = 2;
    public function get_types(): array
    {
        return ['array'];
    }
    public function get_triggers(): int
    {
        return Parser::TRIGGER_SUCCESS;
    }
    public function parse_complete(&$var, Abstract_Value $v, int $trigger): Abstract_Value
    {
        if (!$v instanceof Array_Value) {
            return $v;
        }
        if (\count($var) < 2) {
            return $v;
        }
        // Ensure this is an array of arrays and that all child arrays have the
        // same keys. We don't care about their children - if there's another
        // "table" inside we'll just make another one down the value tab
        $keys = null;
        foreach ($var as $elem) {
            if (!\is_array($elem)) {
                return $v;
            }
            if (null === $keys) {
                if (\count($elem) < self::$min_width || \count($elem) > self::$max_width) {
                    return $v;
                }
                $keys = \array_keys($elem);
            } elseif (\array_keys($elem) !== $keys) {
                return $v;
            }
        }
        $children = $v->get_contents();
        if (!$children) {
            return $v;
        }
        // Ensure none of the child arrays are recursion or depth limit. We
        // don't care if their children are since they are the table cells
        foreach ($children as $childarray) {
            if (!$childarray instanceof Array_Value || empty($childarray->get_contents())) {
                return $v;
            }
        }
        $v->add_representation(new Table_Representation($children), 0);
        return $v;
    }
}