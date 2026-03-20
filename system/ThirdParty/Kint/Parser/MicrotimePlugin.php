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
use Kint\Value\Microtime_Value;
use Kint\Value\Representation\Microtime_Representation;
class Microtime_Plugin extends Abstract_Plugin implements Plugin_Complete_Interface
{
    private static ?array $last = null;
    private static ?float $start = null;
    private static int $times = 0;
    private static ?string $group = null;
    public function get_types(): array
    {
        return ['string', 'double'];
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
        if (\is_string($var)) {
            if ('microtime()' !== $c->get_name() || !\preg_match('/^0\.[0-9]{8} [0-9]{10}$/', $var)) {
                return $v;
            }
            $usec = (int) \substr($var, 2, 6);
            $sec = (int) \substr($var, 11, 10);
        } else {
            if ('microtime(...)' !== $c->get_name()) {
                return $v;
            }
            $sec = (int) \floor($var);
            $usec = $var - $sec;
            $usec = (int) \floor($usec * 1000000);
        }
        $time = $sec + $usec / 1000000;
        if (null !== self::$last) {
            $last_time = self::$last[0] + self::$last[1] / 1000000;
            $lap = $time - $last_time;
            ++self::$times;
        } else {
            $lap = null;
            self::$start = $time;
        }
        self::$last = [$sec, $usec];
        if (null !== $lap) {
            $total = $time - self::$start;
            $r = new Microtime_Representation($sec, $usec, self::get_group(), $lap, $total, self::$times);
        } else {
            $r = new Microtime_Representation($sec, $usec, self::get_group());
        }
        $out = new Microtime_Value($v);
        $out->remove_representation('contents');
        $out->add_representation($r);
        return $out;
    }
    /** @psalm-api */
    public static function clean(): void
    {
        self::$last = null;
        self::$start = null;
        self::$times = 0;
        self::new_group();
    }
    private static function get_group(): string
    {
        if (null === self::$group) {
            return self::new_group();
        }
        return self::$group;
    }
    private static function new_group(): string
    {
        return self::$group = \bin2hex(\random_bytes(4));
    }
}