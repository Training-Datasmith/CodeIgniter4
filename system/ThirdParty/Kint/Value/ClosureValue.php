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
namespace Kint\Value;

use Closure;
use Kint\Utils;
use Kint\Value\Context\Base_Context;
use Kint\Value\Context\Context_Interface;
use ReflectionFunction;
class Closure_Value extends Instance_Value
{
    use Parameter_Holding_Trait;
    /** @psalm-readonly */
    protected ?string $filename;
    /** @psalm-readonly */
    protected ?int $startline;
    public function __construct(Context_Interface $context, Closure $cl)
    {
        parent::__construct($context, \get_class($cl), \spl_object_hash($cl), \spl_object_id($cl));
        /**
         * @psalm-suppress UnnecessaryVarAnnotation
         *
         * @psalm-var ContextInterface $this->context
         * Psalm bug #11113
         */
        $closure = new ReflectionFunction($cl);
        if ($closure->is_user_defined()) {
            $this->filename = $closure->get_file_name();
            $this->startline = $closure->get_start_line();
        } else {
            $this->filename = null;
            $this->startline = null;
        }
        $parameters = [];
        foreach ($closure->get_parameters() as $param) {
            $parameters[] = new Parameter_Bag($param);
        }
        $this->parameters = $parameters;
        if (!$this->context instanceof Base_Context) {
            return;
        }
        if (0 !== $this->context->get_depth()) {
            return;
        }
        $ap = $this->context->get_access_path();
        if (null === $ap) {
            return;
        }
        if (\preg_match('/^\((function|fn)\s*\(/i', $ap, $match)) {
            $this->context->name = \strtolower($match[1]);
        }
    }
    /** @psalm-api */
    public function get_file_name(): ?string
    {
        return $this->filename;
    }
    /** @psalm-api */
    public function get_start_line(): ?int
    {
        return $this->startline;
    }
    public function get_display_size(): ?string
    {
        return null;
    }
    public function get_display_name(): string
    {
        return $this->context->get_name() . '(' . $this->get_params() . ')';
    }
    public function get_display_value(): ?string
    {
        if (null !== $this->filename && null !== $this->startline) {
            return Utils::shorten_path($this->filename) . ':' . $this->startline;
        }
        return parent::get_display_value();
    }
}