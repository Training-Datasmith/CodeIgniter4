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

use InvalidArgumentException;
use Kint\Utils;
use Kint\Value\Abstract_Value;
use Kint\Value\Array_Value;
use Kint\Value\Context\Base_Context;
use Kint\Value\Context\Context_Interface;
use Kint\Value\Representation\Container_Representation;
use Kint\Value\Representation\Profile_Representation;
use Kint\Value\Representation\Value_Representation;
class Array_Limit_Plugin extends Abstract_Plugin implements Plugin_Begin_Interface
{
    /**
     * Maximum size of arrays before limiting.
     */
    public static int $trigger = 1000;
    /**
     * Maximum amount of items to show in a limited array.
     */
    public static int $limit = 50;
    /**
     * Don't limit arrays with string keys.
     */
    public static bool $numeric_only = true;
    public function __construct(Parser $p)
    {
        if (self::$limit < 0) {
            throw new InvalidArgumentException('ArrayLimitPlugin::$limit can not be lower than 0');
        }
        if (self::$limit >= self::$trigger) {
            throw new InvalidArgumentException('ArrayLimitPlugin::$limit can not be lower than ArrayLimitPlugin::$trigger');
        }
        parent::__construct($p);
    }
    public function get_types(): array
    {
        return ['array'];
    }
    public function get_triggers(): int
    {
        return Parser::TRIGGER_BEGIN;
    }
    public function parse_begin(&$var, Context_Interface $c): ?Abstract_Value
    {
        $parser = $this->get_parser();
        $pdepth = $parser->get_depth_limit();
        if (!$pdepth) {
            return null;
        }
        $cdepth = $c->get_depth();
        if ($cdepth >= $pdepth - 1) {
            return null;
        }
        if (\count($var) < self::$trigger) {
            return null;
        }
        if (self::$numeric_only && Utils::is_assoc($var)) {
            return null;
        }
        $slice = \array_slice($var, 0, self::$limit, true);
        $array = $parser->parse($slice, $c);
        if (!$array instanceof Array_Value) {
            return null;
        }
        $base = new Base_Context($c->get_name());
        $base->depth = $pdepth - 1;
        $base->access_path = $c->get_access_path();
        $slice = \array_slice($var, self::$limit, null, true);
        $slice = $parser->parse($slice, $base);
        if (!$slice instanceof Array_Value) {
            return null;
        }
        foreach ($slice->get_contents() as $child) {
            $this->replace_depth_limit($child, $cdepth + 1);
        }
        $out = new Array_Value($c, \count($var), \array_merge($array->get_contents(), $slice->get_contents()));
        $out->flags = $array->flags;
        // Explicitly copy over profile plugin
        $arrayp = $array->get_representation('profiling');
        $slicep = $slice->get_representation('profiling');
        if ($arrayp instanceof Profile_Representation && $slicep instanceof Profile_Representation) {
            $out->add_representation(new Profile_Representation($arrayp->complexity + $slicep->complexity));
        }
        // Add contents. Check is in case some bad plugin empties both $slice and $array
        if ($contents = $out->get_contents()) {
            $out->add_representation(new Container_Representation('Contents', $contents, null, true));
        }
        return $out;
    }
    protected function replace_depth_limit(Abstract_Value $v, int $depth): void
    {
        $c = $v->get_context();
        if ($c instanceof Base_Context) {
            $c->depth = $depth;
        }
        $pdepth = $this->get_parser()->get_depth_limit();
        if ($v->flags & Abstract_Value::FLAG_DEPTH_LIMIT && $pdepth && $depth < $pdepth) {
            $v->flags = $v->flags & ~Abstract_Value::FLAG_DEPTH_LIMIT | Abstract_Value::FLAG_ARRAY_LIMIT;
        }
        $reps = $v->get_representations();
        foreach ($reps as $rep) {
            if ($rep instanceof Container_Representation) {
                foreach ($rep->get_contents() as $child) {
                    $this->replace_depth_limit($child, $depth + 1);
                }
            } elseif ($rep instanceof Value_Representation) {
                $this->replace_depth_limit($rep->get_value(), $depth + 1);
            }
        }
    }
}