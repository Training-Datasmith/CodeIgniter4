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
use Kint\Value\Context\Property_Context;
use Kint\Value\Declared_Callable_Bag;
use Kint\Value\Instance_Value;
use Kint\Value\Method_Value;
use Kint\Value\Representation\Container_Representation;
use ReflectionProperty;
class Class_Hooks_Plugin extends Abstract_Plugin implements Plugin_Complete_Interface
{
    public static bool $verbose = false;
    /** @psalm-var array<class-string, array<string, MethodValue[]>> */
    private array $cache = [];
    /** @psalm-var array<class-string, array<string, MethodValue[]>> */
    private array $cache_verbose = [];
    public function get_types(): array
    {
        return ['object'];
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
        if (!$v instanceof Instance_Value) {
            return $v;
        }
        $props = $v->get_representation('properties');
        if (!$props instanceof Container_Representation) {
            return $v;
        }
        foreach ($props->get_contents() as $prop) {
            $c = $prop->get_context();
            if (!$c instanceof Property_Context || Property_Context::HOOK_NONE === $c->hooks) {
                continue;
            }
            $cname = $c->get_name();
            $cowner = $c->owner_class;
            if (!isset($this->cache_verbose[$cowner][$cname])) {
                $ref = new ReflectionProperty($cowner, $cname);
                $hooks = $ref->get_hooks();
                foreach ($hooks as $hook) {
                    if (!self::$verbose && false === $hook->get_doc_comment()) {
                        continue;
                    }
                    $m = new Method_Value(new Method_Context($hook), new Declared_Callable_Bag($hook));
                    $this->cache_verbose[$cowner][$cname][] = $m;
                    if (false !== $hook->get_doc_comment()) {
                        $this->cache[$cowner][$cname][] = $m;
                    }
                }
                $this->cache[$cowner][$cname] ??= [];
                if (self::$verbose) {
                    $this->cache_verbose[$cowner][$cname] ??= [];
                }
            }
            $cache = self::$verbose ? $this->cache_verbose : $this->cache;
            $cache = $cache[$cowner][$cname] ?? [];
            if (\count($cache)) {
                $prop->add_representation(new Container_Representation('Hooks', $cache, 'propertyhooks'));
            }
        }
        return $v;
    }
}