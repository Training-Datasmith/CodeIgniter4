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

use Code_Igniter\Database\Base_Connection;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\I18n\Time;
/**
 * Class DatetimeCast
 *
 * (PHP) [Time --> string] --> (DB driver) --> (DB column) datetime
 *       [     <-- string] <-- (DB driver) <-- (DB column) datetime
 */
class Datetime_Cast extends Base_Cast
{
    public static function get(mixed $value, array $params = [], ?object $helper = null): Time
    {
        if (!is_string($value)) {
            self::invalid_type_value_error($value);
        }
        if (!$helper instanceof Base_Connection) {
            $message = 'The parameter $helper must be BaseConnection.';
            throw new InvalidArgumentException($message);
        }
        /**
         * @see https://www.php.net/manual/en/datetimeimmutable.createfromformat.php#datetimeimmutable.createfromformat.parameters
         */
        $format = self::get_date_time_format($params, $helper);
        return Time::create_from_format($format, $value);
    }
    public static function set(mixed $value, array $params = [], ?object $helper = null): string
    {
        if (!$value instanceof Time) {
            self::invalid_type_value_error($value);
        }
        if (!$helper instanceof Base_Connection) {
            $message = 'The parameter $helper must be BaseConnection.';
            throw new InvalidArgumentException($message);
        }
        $format = self::get_date_time_format($params, $helper);
        return $value->format($format);
    }
    /**
     * Gets DateTime format from the DB connection.
     *
     * @param list<string> $params Additional param
     */
    protected static function get_date_time_format(array $params, Base_Connection $db): string
    {
        return match ($params[0] ?? '') {
            '' => $db->date_format['datetime'],
            'ms' => $db->date_format['datetime-ms'],
            'us' => $db->date_format['datetime-us'],
            default => throw new InvalidArgumentException('Invalid parameter: ' . $params[0]),
        };
    }
}