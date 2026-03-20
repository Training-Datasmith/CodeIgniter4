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
 * Class FloatCast
 *
 * (PHP) [float --> float       ] --> (DB driver) --> (DB column) float
 *       [      <-- float|string] <-- (DB driver) <-- (DB column) float
 */
class Float_Cast extends Base_Cast
{
    public static function get(mixed $value, array $params = [], ?object $helper = null): float
    {
        if (!is_float($value) && !is_string($value)) {
            self::invalid_type_value_error($value);
        }
        return (float) $value;
    }
}