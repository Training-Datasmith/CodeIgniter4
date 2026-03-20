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

use Dom\Named_Node_Map;
use Dom\Node_List;
use Dom_Named_Node_Map;
use Dom_Node_List;
use Kint\Value\Abstract_Value;
use Kint\Value\Array_Value;
use Kint\Value\Context\Base_Context;
use Kint\Value\Instance_Value;
use Kint\Value\Representation\Container_Representation;
use Kint\Value\Representation\Value_Representation;
use Kint\Value\Uninitialized_Value;
use mysqli_result;
use PDOStatement;
use Simple_Xml_Element;
use Spl_File_Object;
use Throwable;
use Traversable;
class Iterator_Plugin extends Abstract_Plugin implements Plugin_Complete_Interface
{
    /**
     * List of classes and interfaces to blacklist.
     *
     * Certain classes (Such as PDOStatement) irreversibly lose information
     * when traversed. Others are just huge. Either way, put them in here
     * and you won't have to worry about them being parsed.
     *
     * @psalm-var class-string[]
     */
    public static array $blacklist = [Named_Node_Map::class, Node_List::class, Dom_Named_Node_Map::class, Dom_Node_List::class, mysqli_result::class, PDOStatement::class, Simple_Xml_Element::class, Spl_File_Object::class];
    public function get_types(): array
    {
        return ['object'];
    }
    public function get_triggers(): int
    {
        return Parser::TRIGGER_SUCCESS;
    }
    public function parse_complete(&$var, Abstract_Value $v, int $trigger): Abstract_Value
    {
        if (!$var instanceof Traversable || !$v instanceof Instance_Value || $v->get_representation('iterator')) {
            return $v;
        }
        $c = $v->get_context();
        foreach (self::$blacklist as $class) {
            if ($var instanceof $class) {
                $base = new Base_Context($class . ' Iterator Contents');
                $base->depth = $c->get_depth() + 1;
                if (null !== $ap = $c->get_access_path()) {
                    $base->access_path = 'iterator_to_array(' . $ap . ', false)';
                }
                $b = new Uninitialized_Value($base);
                $b->flags |= Abstract_Value::FLAG_BLACKLIST;
                $v->add_representation(new Value_Representation('Iterator', $b));
                return $v;
            }
        }
        try {
            $data = \iterator_to_array($var, false);
        } catch (Throwable $t) {
            return $v;
        }
        if (!\count($data)) {
            return $v;
        }
        $base = new Base_Context('Iterator Contents');
        $base->depth = $c->get_depth();
        if (null !== $ap = $c->get_access_path()) {
            $base->access_path = 'iterator_to_array(' . $ap . ', false)';
        }
        $iter_val = $this->get_parser()->parse($data, $base);
        // Since we didn't get TRIGGER_DEPTH_LIMIT and set the iterator to the
        // same depth we can assume at least 1 level deep will exist
        if ($iter_val instanceof Array_Value && $iterator_items = $iter_val->get_contents()) {
            $r = new Container_Representation('Iterator', $iterator_items);
            $iterator_items = \array_values($iterator_items);
        } else {
            $r = new Value_Representation('Iterator', $iter_val);
            $iterator_items = [$iter_val];
        }
        if ((bool) $v->get_children()) {
            $v->add_representation($r);
        } else {
            $v->set_children($iterator_items);
            $v->add_representation($r, 0);
        }
        return $v;
    }
}