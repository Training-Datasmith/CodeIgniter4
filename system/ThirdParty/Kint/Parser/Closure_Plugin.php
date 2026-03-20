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

use Closure;
use Kint\Value\Abstract_Value;
use Kint\Value\Closure_Value;
use Kint\Value\Context\Base_Context;
use Kint\Value\Representation\Container_Representation;
use ReflectionFunction;
use Reflection_Reference;
class Closure_Plugin extends Abstract_Plugin implements Plugin_Complete_Interface
{
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
        if (!$var instanceof Closure) {
            return $v;
        }
        $c = $v->get_context();
        $object = new Closure_Value($c, $var);
        $object->flags = $v->flags;
        $object->append_representations($v->get_representations());
        $object->remove_representation('properties');
        $closure = new ReflectionFunction($var);
        $statics = [];
        if ($v = $closure->get_closure_this()) {
            $statics = ['this' => $v];
        }
        $statics = $statics + $closure->get_static_variables();
        $cdepth = $c->get_depth();
        if (\count($statics)) {
            $statics_parsed = [];
            $parser = $this->get_parser();
            foreach ($statics as $name => $_) {
                $base = new Base_Context('$' . $name);
                $base->depth = $cdepth + 1;
                $base->reference = null !== Reflection_Reference::from_array_element($statics, $name);
                $statics_parsed[$name] = $parser->parse($statics[$name], $base);
            }
            $object->add_representation(new Container_Representation('Uses', $statics_parsed), 0);
        }
        return $object;
    }
}