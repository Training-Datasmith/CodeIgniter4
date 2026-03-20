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
use Kint\Value\Context\Context_Interface;
/**
 * @psalm-import-type ParserTrigger from Parser
 *
 * @psalm-api
 */
class Proxy_Plugin implements Plugin_Begin_Interface, Plugin_Complete_Interface
{
    protected array $types;
    /** @psalm-var ParserTrigger */
    protected int $triggers;
    /** @psalm-var callable */
    protected $callback;
    private ?Parser $parser = null;
    /**
     * @psalm-param ParserTrigger $triggers
     * @psalm-param callable $callback
     */
    public function __construct(array $types, int $triggers, $callback)
    {
        $this->types = $types;
        $this->triggers = $triggers;
        $this->callback = $callback;
    }
    public function set_parser(Parser $p): void
    {
        $this->parser = $p;
    }
    public function get_types(): array
    {
        return $this->types;
    }
    public function get_triggers(): int
    {
        return $this->triggers;
    }
    public function parse_begin(&$var, Context_Interface $c): ?Abstract_Value
    {
        return \call_user_func_array($this->callback, [&$var, $c, Parser::TRIGGER_BEGIN, $this->parser]);
    }
    public function parse_complete(&$var, Abstract_Value $v, int $trigger): Abstract_Value
    {
        return \call_user_func_array($this->callback, [&$var, $v, $trigger, $this->parser]);
    }
}