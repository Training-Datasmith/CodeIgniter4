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

use Code_Igniter\I18n\Time;
/**
 * Class TimestampCast
 *
 * (PHP) [Time --> int       ] --> (DB driver) --> (DB column) int
 *       [     <-- int|string] <-- (DB driver) <-- (DB column) int
 */
class Timestamp_Cast extends Base_Cast
{
    public static function get(mixed $value, array $params = [], ?object $helper = null): Time
    {
        if (!is_int($value) && !is_string($value)) {
            self::invalid_type_value_error($value);
        }
        return Time::create_from_timestamp((int) $value, date_default_timezone_get());
    }
    public static function set(mixed $value, array $params = [], ?object $helper = null): int
    {
        if (!$value instanceof Time) {
            self::invalid_type_value_error($value);
        }
        return $value->get_timestamp();
    }
}