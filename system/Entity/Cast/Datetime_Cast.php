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
namespace Code_Igniter\Entity\Cast;

use Code_Igniter\I18n\Time;
use DateTimeInterface;
use Exception;
class Datetime_Cast extends Base_Cast
{
    /**
     * {@inheritDoc}
     *
     * @return Time
     *
     * @throws Exception
     */
    public static function get($value, array $params = [])
    {
        if ($value instanceof Time) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return Time::create_from_instance($value);
        }
        if (is_numeric($value)) {
            return Time::create_from_timestamp((int) $value, date_default_timezone_get());
        }
        if (is_string($value)) {
            return Time::parse($value);
        }
        return $value;
    }
}