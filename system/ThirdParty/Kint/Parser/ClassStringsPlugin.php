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
use Kint\Value\Instance_Value;
use ReflectionClass;
class Class_Strings_Plugin extends Abstract_Plugin implements Plugin_Complete_Interface
{
    public static array $blacklist = [];
    protected Class_Methods_Plugin $methods_plugin;
    protected Class_Statics_Plugin $statics_plugin;
    public function __construct(Parser $parser)
    {
        parent::__construct($parser);
        $this->methods_plugin = new Class_Methods_Plugin($parser);
        $this->statics_plugin = new Class_Statics_Plugin($parser);
    }
    public function set_parser(Parser $p): void
    {
        parent::set_parser($p);
        $this->methods_plugin->set_parser($p);
        $this->statics_plugin->set_parser($p);
    }
    public function get_types(): array
    {
        return ['string'];
    }
    public function get_triggers(): int
    {
        return Parser::TRIGGER_SUCCESS;
    }
    public function parse_complete(&$var, Abstract_Value $v, int $trigger): Abstract_Value
    {
        $c = $v->get_context();
        if ($c->get_depth() > 0) {
            return $v;
        }
        if (!\class_exists($var, true)) {
            return $v;
        }
        if (\in_array($var, self::$blacklist, true)) {
            return $v;
        }
        $r = new ReflectionClass($var);
        $fake_c = new Base_Context($c->get_name());
        $fake_c->access_path = null;
        $fake_v = new Instance_Value($fake_c, $r->get_name(), 'badhash', -1);
        $fake_var = null;
        $fake_v = $this->methods_plugin->parse_complete($fake_var, $fake_v, Parser::TRIGGER_SUCCESS);
        $fake_v = $this->statics_plugin->parse_complete($fake_var, $fake_v, Parser::TRIGGER_SUCCESS);
        foreach (['methods', 'static_methods', 'statics', 'constants'] as $rep) {
            if ($rep = $fake_v->get_representation($rep)) {
                $v->add_representation($rep);
            }
        }
        return $v;
    }
}