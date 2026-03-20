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
use Kint\Value\Context\Class_Const_Context;
use Kint\Value\Context\Class_Declared_Context;
use Kint\Value\Context\Static_Property_Context;
use Kint\Value\Instance_Value;
use Kint\Value\Representation\Container_Representation;
use Kint\Value\Uninitialized_Value;
use ReflectionClass;
use Reflection_Class_Constant;
use ReflectionProperty;
use Unit_Enum;
class Class_Statics_Plugin extends Abstract_Plugin implements Plugin_Complete_Interface
{
    /** @psalm-var array<class-string, array<1|0, array<AbstractValue>>> */
    private array $cache = [];
    public function get_types(): array
    {
        return ['object'];
    }
    public function get_triggers(): int
    {
        return Parser::TRIGGER_SUCCESS;
    }
    /**
     * @psalm-template T of AbstractValue
     *
     * @psalm-param mixed $var
     * @psalm-param T $v
     *
     * @psalm-return T
     */
    public function parse_complete(&$var, Abstract_Value $v, int $trigger): Abstract_Value
    {
        if (!$v instanceof Instance_Value) {
            return $v;
        }
        $deep = 0 === $this->get_parser()->get_depth_limit();
        $r = new ReflectionClass($v->get_class_name());
        if ($statics = $this->get_statics($r, $v->get_context()->get_depth() + 1)) {
            $v->add_representation(new Container_Representation('Static properties', \array_values($statics), 'statics'));
        }
        if ($consts = $this->get_cached_constants($r, $deep)) {
            $v->add_representation(new Container_Representation('Class constants', \array_values($consts), 'constants'));
        }
        return $v;
    }
    /** @psalm-return array<AbstractValue> */
    private function get_statics(ReflectionClass $r, int $depth): array
    {
        $cdepth = $depth ?: 1;
        $class = $r->get_name();
        $parent = $r->get_parent_class();
        $parent_statics = $parent ? $this->get_statics($parent, $depth) : [];
        $statics = [];
        foreach ($r->get_properties(ReflectionProperty::IS_STATIC) as $pr) {
            $canon_name = \strtolower($pr->get_declaring_class()->name . '::' . $pr->name);
            if ($pr->get_declaring_class()->name === $class) {
                $statics[$canon_name] = $this->build_static_value($pr, $cdepth);
            } elseif (isset($parent_statics[$canon_name])) {
                $statics[$canon_name] = $parent_statics[$canon_name];
                unset($parent_statics[$canon_name]);
            } else {
                // This should never happen since abstract static properties can't exist
                $statics[$canon_name] = $this->build_static_value($pr, $cdepth);
                // @codeCoverageIgnore
            }
        }
        foreach ($parent_statics as $canon_name => $value) {
            $statics[$canon_name] = $value;
        }
        return $statics;
    }
    private function build_static_value(ReflectionProperty $pr, int $depth): Abstract_Value
    {
        $context = new Static_Property_Context($pr->name, $pr->get_declaring_class()->name, Class_Declared_Context::ACCESS_PUBLIC);
        $context->depth = $depth;
        $context->final = KINT_PHP84 && $pr->is_final();
        if ($pr->is_protected()) {
            $context->access = Class_Declared_Context::ACCESS_PROTECTED;
        } elseif ($pr->is_private()) {
            $context->access = Class_Declared_Context::ACCESS_PRIVATE;
        }
        $parser = $this->get_parser();
        if ($context->is_accessible($parser->get_caller_class())) {
            $context->access_path = '\\' . $context->owner_class . '::$' . $context->name;
        }
        if (KINT_PHP81 === false) {
            $pr->set_accessible(true);
        }
        /**
         * @psalm-suppress TooFewArguments
         * Appears to have been fixed in master.
         */
        if (!$pr->is_initialized()) {
            $context->access_path = null;
            return new Uninitialized_Value($context);
        }
        $val = $pr->get_value();
        $out = $this->get_parser()->parse($val, $context);
        $context->access_path = null;
        return $out;
    }
    /** @psalm-return array<AbstractValue> */
    private function get_cached_constants(ReflectionClass $r, bool $deep): array
    {
        $parser = $this->get_parser();
        $cdepth = $parser->get_depth_limit() ?: 1;
        $deepkey = (int) $deep;
        $class = $r->get_name();
        // Separate cache for dumping with/without depth limit
        // This means we can do immediate depth limit on normal dumps
        if (!isset($this->cache[$class][$deepkey])) {
            $consts = [];
            $parent_consts = [];
            if ($parent = $r->get_parent_class()) {
                $parent_consts = $this->get_cached_constants($parent, $deep);
            }
            foreach ($r->get_constants() as $name => $val) {
                $cr = new Reflection_Class_Constant($class, $name);
                // Skip enum constants
                if ($cr->class === $class && \is_a($class, Unit_Enum::class, true)) {
                    continue;
                }
                $canon_name = \strtolower($cr->get_declaring_class()->name . '::' . $name);
                if ($cr->get_declaring_class()->name === $class) {
                    $context = $this->build_const_context($cr);
                    $context->depth = $cdepth;
                    $consts[$canon_name] = $parser->parse($val, $context);
                    $context->access_path = null;
                } elseif (isset($parent_consts[$canon_name])) {
                    $consts[$canon_name] = $parent_consts[$canon_name];
                } else {
                    $context = $this->build_const_context($cr);
                    $context->depth = $cdepth;
                    $consts[$canon_name] = $parser->parse($val, $context);
                    $context->access_path = null;
                }
                unset($parent_consts[$canon_name]);
            }
            $this->cache[$class][$deepkey] = $consts + $parent_consts;
        }
        return $this->cache[$class][$deepkey];
    }
    private function build_const_context(Reflection_Class_Constant $cr): Class_Const_Context
    {
        $context = new Class_Const_Context($cr->name, $cr->get_declaring_class()->name, Class_Declared_Context::ACCESS_PUBLIC);
        $context->final = KINT_PHP81 && $cr->is_final();
        if ($cr->is_protected()) {
            $context->access = Class_Declared_Context::ACCESS_PROTECTED;
        } elseif ($cr->is_private()) {
            $context->access = Class_Declared_Context::ACCESS_PRIVATE;
        } else {
            $context->access_path = '\\' . $context->owner_class . '::' . $context->name;
        }
        return $context;
    }
}