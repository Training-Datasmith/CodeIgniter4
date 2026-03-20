<?php

declare (strict_types=1);
/**
 * This file is part of CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */
namespace Code_Igniter\Data_Caster\Cast;

use Code_Igniter\HTTP\URI;
/**
 * Class URICast
 *
 * (PHP) [URI --> string] --> (DB driver) --> (DB column) string
 *       [    <-- string] <-- (DB driver) <-- (DB column) string
 */
class Uri_Cast extends Base_Cast
{
    public static function get(mixed $value, array $params = [], ?object $helper = null): URI
    {
        if (!is_string($value)) {
            self::invalid_type_value_error($value);
        }
        return new URI($value);
    }
    public static function set(mixed $value, array $params = [], ?object $helper = null): string
    {
        if (!$value instanceof URI) {
            self::invalid_type_value_error($value);
        }
        return (string) $value;
    }
}