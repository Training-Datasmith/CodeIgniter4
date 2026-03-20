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
use Kint\Value\Enum_Value;
use Kint\Value\Representation\Container_Representation;
use Unit_Enum;
class Enum_Plugin extends Abstract_Plugin implements Plugin_Complete_Interface
{
    private array $cache = [];
    public function get_types(): array
    {
        return ['object'];
    }
    public function get_triggers(): int
    {
        if (!KINT_PHP81) {
            return Parser::TRIGGER_NONE;
        }
        return Parser::TRIGGER_SUCCESS;
    }
    public function parse_complete(&$var, Abstract_Value $v, int $trigger): Abstract_Value
    {
        if (!$var instanceof Unit_Enum) {
            return $v;
        }
        $c = $v->get_context();
        $class = \get_class($var);
        if (!isset($this->cache[$class])) {
            $contents = [];
            foreach ($var->cases() as $case) {
                $base = new Base_Context($case->name);
                $base->access_path = '\\' . $class . '::' . $case->name;
                $base->depth = $c->get_depth() + 1;
                $contents[] = new Enum_Value($base, $case);
            }
            /** @psalm-var non-empty-array<EnumValue> $contents */
            $this->cache[$class] = new Container_Representation('Enum values', $contents, 'enum');
        }
        $object = new Enum_Value($c, $var);
        $object->flags = $v->flags;
        $object->append_representations($v->get_representations());
        $object->add_representation($this->cache[$class], 0);
        return $object;
    }
}