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

/**
 * Class IntegerCast
 *
 * (PHP) [int --> int       ] --> (DB driver) --> (DB column) int
 *       [    <-- int|string] <-- (DB driver) <-- (DB column) int
 */
class Integer_Cast extends Base_Cast
{
    public static function get(mixed $value, array $params = [], ?object $helper = null): int
    {
        if (!is_string($value) && !is_int($value)) {
            self::invalid_type_value_error($value);
        }
        return (int) $value;
    }
}