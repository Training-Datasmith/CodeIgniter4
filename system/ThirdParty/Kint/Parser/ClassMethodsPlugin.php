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
use Kint\Value\Context\Method_Context;
use Kint\Value\Declared_Callable_Bag;
use Kint\Value\Instance_Value;
use Kint\Value\Method_Value;
use Kint\Value\Representation\Container_Representation;
use ReflectionClass;
use ReflectionMethod;
class Class_Methods_Plugin extends Abstract_Plugin implements Plugin_Complete_Interface
{
    public static bool $show_access_path = true;
    /**
     * Whether to go out of the way to show constructor paths
     * when the instance isn't accessible.
     *
     * Disabling this improves performance.
     */
    public static bool $show_constructor_path = false;
    /** @psalm-var array<class-string, MethodValue[]> */
    private array $instance_cache = [];
    /** @psalm-var array<class-string, MethodValue[]> */
    private array $static_cache = [];
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
        $class = $v->get_class_name();
        $scope = $this->get_parser()->get_caller_class();
        if ($contents = $this->get_cached_methods($class)) {
            if (self::$show_access_path) {
                if (null !== $v->get_context()->get_access_path()) {
                    // If we have an access path we can generate them for the children
                    foreach ($contents as $key => $val) {
                        if ($val->get_context()->is_accessible($scope)) {
                            $val = clone $val;
                            $val->get_context()->set_access_path_from_parent($v);
                            $contents[$key] = $val;
                        }
                    }
                } elseif (self::$show_constructor_path && isset($contents['__construct'])) {
                    // __construct is the only exception: The only non-static method
                    // that can be called without access to the parent instance.
                    // Technically I guess it really is a static method but so long
                    // as PHP continues to refer to it as a normal one so will we.
                    $val = $contents['__construct'];
                    if ($val->get_context()->is_accessible($scope)) {
                        $val = clone $val;
                        $val->get_context()->set_access_path_from_parent($v);
                        $contents['__construct'] = $val;
                    }
                }
            }
            $v->add_representation(new Container_Representation('Methods', $contents));
        }
        if ($contents = $this->get_cached_static_methods($class)) {
            $v->add_representation(new Container_Representation('Static methods', $contents));
        }
        return $v;
    }
    /**
     * @psalm-param class-string $class
     *
     * @psalm-return MethodValue[]
     */
    private function get_cached_methods(string $class): array
    {
        if (!isset($this->instance_cache[$class])) {
            $methods = [];
            $r = new ReflectionClass($class);
            $parent_methods = [];
            if ($parent = \get_parent_class($class)) {
                $parent_methods = $this->get_cached_methods($parent);
            }
            foreach ($r->get_methods() as $mr) {
                if ($mr->is_static()) {
                    continue;
                }
                $canon_name = \strtolower($mr->name);
                if ($mr->is_private() && '__construct' !== $canon_name) {
                    $canon_name = \strtolower($mr->get_declaring_class()->name) . '::' . $canon_name;
                }
                if ($mr->get_declaring_class()->name === $class) {
                    $method = new Method_Value(new Method_Context($mr), new Declared_Callable_Bag($mr));
                    $methods[$canon_name] = $method;
                    unset($parent_methods[$canon_name]);
                } elseif (isset($parent_methods[$canon_name])) {
                    $method = $parent_methods[$canon_name];
                    unset($parent_methods[$canon_name]);
                    if (!$method->get_context()->inherited) {
                        $method = clone $method;
                        $method->get_context()->inherited = true;
                    }
                    $methods[$canon_name] = $method;
                } elseif ($mr->get_declaring_class()->is_interface()) {
                    $c = new Method_Context($mr);
                    $c->inherited = true;
                    $methods[$canon_name] = new Method_Value($c, new Declared_Callable_Bag($mr));
                }
            }
            foreach ($parent_methods as $name => $method) {
                if (!$method->get_context()->inherited) {
                    $method = clone $method;
                    $method->get_context()->inherited = true;
                }
                if ('__construct' === $name) {
                    $methods['__construct'] = $method;
                } else {
                    $methods[] = $method;
                }
            }
            $this->instance_cache[$class] = $methods;
        }
        return $this->instance_cache[$class];
    }
    /**
     * @psalm-param class-string $class
     *
     * @psalm-return MethodValue[]
     */
    private function get_cached_static_methods(string $class): array
    {
        if (!isset($this->static_cache[$class])) {
            $methods = [];
            $r = new ReflectionClass($class);
            $parent_methods = [];
            if ($parent = \get_parent_class($class)) {
                $parent_methods = $this->get_cached_static_methods($parent);
            }
            foreach ($r->get_methods(ReflectionMethod::IS_STATIC) as $mr) {
                $canon_name = \strtolower($mr->get_declaring_class()->name . '::' . $mr->name);
                if ($mr->get_declaring_class()->name === $class) {
                    $method = new Method_Value(new Method_Context($mr), new Declared_Callable_Bag($mr));
                    $methods[$canon_name] = $method;
                } elseif (isset($parent_methods[$canon_name])) {
                    $methods[$canon_name] = $parent_methods[$canon_name];
                } elseif ($mr->get_declaring_class()->is_interface()) {
                    $c = new Method_Context($mr);
                    $c->inherited = true;
                    $methods[$canon_name] = new Method_Value($c, new Declared_Callable_Bag($mr));
                }
                unset($parent_methods[$canon_name]);
            }
            $this->static_cache[$class] = $methods + $parent_methods;
        }
        return $this->static_cache[$class];
    }
}