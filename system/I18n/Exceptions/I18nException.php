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
namespace Code_Igniter\I18n\Exceptions;

use Code_Igniter\Exceptions\Framework_Exception;
/**
 * I18nException
 */
class I18n_Exception extends Framework_Exception
{
    /**
     * Thrown when createFromFormat fails to receive a valid
     * DateTime back from DateTime::createFromFormat.
     *
     * @return static
     */
    public static function for_invalid_format(string $format)
    {
        return new static(lang('Time.invalidFormat', [$format]));
    }
    /**
     * Thrown when the numeric representation of the month falls
     * outside the range of allowed months.
     *
     * @return static
     */
    public static function for_invalid_month(string $month)
    {
        return new static(lang('Time.invalidMonth', [$month]));
    }
    /**
     * Thrown when the supplied day falls outside the range
     * of allowed days.
     *
     * @return static
     */
    public static function for_invalid_day(string $day)
    {
        return new static(lang('Time.invalidDay', [$day]));
    }
    /**
     * Thrown when the day provided falls outside the allowed
     * last day for the given month.
     *
     * @return static
     */
    public static function for_invalid_over_day(string $last_day, string $day)
    {
        return new static(lang('Time.invalidOverDay', [$last_day, $day]));
    }
    /**
     * Thrown when the supplied hour falls outside the
     * range of allowed hours.
     *
     * @return static
     */
    public static function for_invalid_hour(string $hour)
    {
        return new static(lang('Time.invalidHours', [$hour]));
    }
    /**
     * Thrown when the supplied minutes falls outside the
     * range of allowed minutes.
     *
     * @return static
     */
    public static function for_invalid_minutes(string $minutes)
    {
        return new static(lang('Time.invalidMinutes', [$minutes]));
    }
    /**
     * Thrown when the supplied seconds falls outside the
     * range of allowed seconds.
     *
     * @return static
     */
    public static function for_invalid_seconds(string $seconds)
    {
        return new static(lang('Time.invalidSeconds', [$seconds]));
    }
}