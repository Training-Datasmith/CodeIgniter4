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
use Kint\Value\Context\Context_Interface;
use Kint\Value\Fixed_Width_Value;
use Kint\Value\Instance_Value;
use Kint\Value\Representation\Container_Representation;
use Kint\Value\Representation\Profile_Representation;
/** @psalm-api */
class Profile_Plugin extends Abstract_Plugin implements Plugin_Begin_Interface, Plugin_Complete_Interface
{
    protected array $instance_counts = [];
    protected array $instance_complexity = [];
    protected array $instance_count_stack = [];
    protected array $class_complexity = [];
    protected array $class_count_stack = [];
    public function get_types(): array
    {
        return ['string', 'object', 'array', 'integer', 'double', 'resource'];
    }
    public function get_triggers(): int
    {
        return Parser::TRIGGER_BEGIN | Parser::TRIGGER_COMPLETE;
    }
    public function parse_begin(&$var, Context_Interface $c): ?Abstract_Value
    {
        if (0 === $c->get_depth()) {
            $this->instance_counts = [];
            $this->instance_complexity = [];
            $this->instance_count_stack = [];
            $this->class_complexity = [];
            $this->class_count_stack = [];
        }
        if (\is_object($var)) {
            $hash = \spl_object_hash($var);
            $this->instance_counts[$hash] ??= 0;
            $this->instance_complexity[$hash] ??= 0;
            $this->instance_count_stack[$hash] ??= 0;
            if (0 === $this->instance_count_stack[$hash]) {
                /**
                 * @psalm-suppress PossiblyFalseIterator
                 * Psalm bug #11392
                 */
                foreach (\class_parents($var) as $class) {
                    $this->class_count_stack[$class] ??= 0;
                    ++$this->class_count_stack[$class];
                }
                /**
                 * @psalm-suppress PossiblyFalseIterator
                 * Psalm bug #11392
                 */
                foreach (\class_implements($var) as $iface) {
                    $this->class_count_stack[$iface] ??= 0;
                    ++$this->class_count_stack[$iface];
                }
            }
            ++$this->instance_count_stack[$hash];
        }
        return null;
    }
    public function parse_complete(&$var, Abstract_Value $v, int $trigger): Abstract_Value
    {
        if ($v instanceof Instance_Value) {
            --$this->instance_count_stack[$v->get_spl_object_hash()];
            if (0 === $this->instance_count_stack[$v->get_spl_object_hash()]) {
                /**
                 * @psalm-suppress PossiblyFalseIterator
                 * Psalm bug #11392
                 */
                foreach (\class_parents($var) as $class) {
                    --$this->class_count_stack[$class];
                }
                /**
                 * @psalm-suppress PossiblyFalseIterator
                 * Psalm bug #11392
                 */
                foreach (\class_implements($var) as $iface) {
                    --$this->class_count_stack[$iface];
                }
            }
        }
        // Don't check subs if we're in recursion or array limit
        if (~$trigger & Parser::TRIGGER_SUCCESS) {
            return $v;
        }
        $sub_complexity = 1;
        foreach ($v->get_representations() as $rep) {
            if ($rep instanceof Container_Representation) {
                foreach ($rep->get_contents() as $value) {
                    $profile = $value->get_representation('profiling');
                    $sub_complexity += $profile instanceof Profile_Representation ? $profile->complexity : 1;
                }
            } else {
                ++$sub_complexity;
            }
        }
        if ($v instanceof Instance_Value) {
            ++$this->instance_counts[$v->get_spl_object_hash()];
            if (0 === $this->instance_count_stack[$v->get_spl_object_hash()]) {
                $this->instance_complexity[$v->get_spl_object_hash()] += $sub_complexity;
                $this->class_complexity[$v->get_class_name()] ??= 0;
                $this->class_complexity[$v->get_class_name()] += $sub_complexity;
                /**
                 * @psalm-suppress PossiblyFalseIterator
                 * Psalm bug #11392
                 */
                foreach (\class_parents($var) as $class) {
                    $this->class_complexity[$class] ??= 0;
                    if (0 === $this->class_count_stack[$class]) {
                        $this->class_complexity[$class] += $sub_complexity;
                    }
                }
                /**
                 * @psalm-suppress PossiblyFalseIterator
                 * Psalm bug #11392
                 */
                foreach (\class_implements($var) as $iface) {
                    $this->class_complexity[$iface] ??= 0;
                    if (0 === $this->class_count_stack[$iface]) {
                        $this->class_complexity[$iface] += $sub_complexity;
                    }
                }
            }
        }
        if (0 === $v->get_context()->get_depth()) {
            $contents = [];
            \arsort($this->class_complexity);
            foreach ($this->class_complexity as $name => $complexity) {
                $contents[] = new Fixed_Width_Value(new Base_Context($name), $complexity);
            }
            if ($contents) {
                $v->add_representation(new Container_Representation('Class complexity', $contents), 0);
            }
        }
        $rep = new Profile_Representation($sub_complexity);
        /** @psalm-suppress UnsupportedReferenceUsage */
        if ($v instanceof Instance_Value) {
            $rep->instance_counts =& $this->instance_counts[$v->get_spl_object_hash()];
            $rep->instance_complexity =& $this->instance_complexity[$v->get_spl_object_hash()];
        }
        $v->add_representation($rep, 0);
        return $v;
    }
}