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
namespace Code_Igniter\I18n;

use DateTime;
use Intl_Calendar;
/**
 * @property-read float|int $days
 * @property-read float|int $hours
 * @property-read float|int $minutes
 * @property-read float|int $months
 * @property-read int       $seconds
 * @property-read float|int $weeks
 * @property-read float|int $years
 *
 * @see \CodeIgniter\I18n\TimeDifferenceTest
 */
class Time_Difference
{
    /**
     * The timestamp of the "current" time.
     *
     * @var IntlCalendar
     */
    protected $current_time;
    /**
     * The timestamp to compare the current time to.
     *
     * @var float
     */
    protected $test_time;
    /**
     * Eras.
     *
     * @var float
     */
    protected $eras = 0;
    /**
     * Years.
     *
     * @var float
     */
    protected $years = 0;
    /**
     * Months.
     *
     * @var float
     */
    protected $months = 0;
    /**
     * Weeks.
     *
     * @var int
     */
    protected $weeks = 0;
    /**
     * Days.
     *
     * @var int
     */
    protected $days = 0;
    /**
     * Hours.
     *
     * @var int
     */
    protected $hours = 0;
    /**
     * Minutes.
     *
     * @var int
     */
    protected $minutes = 0;
    /**
     * Seconds.
     *
     * @var int
     */
    protected $seconds = 0;
    /**
     * Difference in seconds.
     *
     * @var int
     */
    protected $difference;
    /**
     * Note: both parameters are required to be in the same timezone. No timezone
     * shifting is done internally.
     */
    public function __construct(DateTime $current_time, DateTime $test_time)
    {
        $this->difference = $current_time->get_timestamp() - $test_time->get_timestamp();
        $current = Intl_Calendar::from_date_time($current_time);
        $time = Intl_Calendar::from_date_time($test_time)->get_time();
        $this->current_time = $current;
        $this->test_time = $time;
    }
    /**
     * Returns the number of years of difference between the two.
     *
     * @return float|int
     */
    public function get_years(bool $raw = false)
    {
        if ($raw) {
            return $this->difference / YEAR;
        }
        $time = clone $this->current_time;
        return $time->field_difference($this->test_time, Intl_Calendar::FIELD_YEAR);
    }
    /**
     * Returns the number of months difference between the two dates.
     *
     * @return float|int
     */
    public function get_months(bool $raw = false)
    {
        if ($raw) {
            return $this->difference / MONTH;
        }
        $time = clone $this->current_time;
        return $time->field_difference($this->test_time, Intl_Calendar::FIELD_MONTH);
    }
    /**
     * Returns the number of weeks difference between the two dates.
     *
     * @return float|int
     */
    public function get_weeks(bool $raw = false)
    {
        if ($raw) {
            return $this->difference / WEEK;
        }
        $time = clone $this->current_time;
        return (int) ($time->field_difference($this->test_time, Intl_Calendar::FIELD_DAY_OF_YEAR) / 7);
    }
    /**
     * Returns the number of days difference between the two dates.
     *
     * @return float|int
     */
    public function get_days(bool $raw = false)
    {
        if ($raw) {
            return $this->difference / DAY;
        }
        $time = clone $this->current_time;
        return $time->field_difference($this->test_time, Intl_Calendar::FIELD_DAY_OF_YEAR);
    }
    /**
     * Returns the number of hours difference between the two dates.
     *
     * @return float|int
     */
    public function get_hours(bool $raw = false)
    {
        if ($raw) {
            return $this->difference / HOUR;
        }
        $time = clone $this->current_time;
        return $time->field_difference($this->test_time, Intl_Calendar::FIELD_HOUR_OF_DAY);
    }
    /**
     * Returns the number of minutes difference between the two dates.
     *
     * @return float|int
     */
    public function get_minutes(bool $raw = false)
    {
        if ($raw) {
            return $this->difference / MINUTE;
        }
        $time = clone $this->current_time;
        return $time->field_difference($this->test_time, Intl_Calendar::FIELD_MINUTE);
    }
    /**
     * Returns the number of seconds difference between the two dates.
     *
     * @return int
     */
    public function get_seconds(bool $raw = false)
    {
        if ($raw) {
            return $this->difference;
        }
        $time = clone $this->current_time;
        return $time->field_difference($this->test_time, Intl_Calendar::FIELD_SECOND);
    }
    /**
     * Convert the time to human readable format
     */
    public function humanize(?string $locale = null): string
    {
        $current = clone $this->current_time;
        $years = $current->field_difference($this->test_time, Intl_Calendar::FIELD_YEAR);
        $months = $current->field_difference($this->test_time, Intl_Calendar::FIELD_MONTH);
        $days = $current->field_difference($this->test_time, Intl_Calendar::FIELD_DAY_OF_YEAR);
        $hours = $current->field_difference($this->test_time, Intl_Calendar::FIELD_HOUR_OF_DAY);
        $minutes = $current->field_difference($this->test_time, Intl_Calendar::FIELD_MINUTE);
        $phrase = null;
        if ($years !== 0) {
            $phrase = lang('Time.years', [abs($years)], $locale);
            $before = $years < 0;
        } elseif ($months !== 0) {
            $phrase = lang('Time.months', [abs($months)], $locale);
            $before = $months < 0;
        } elseif ($days !== 0 && abs($days) >= 7) {
            $weeks = ceil($days / 7);
            $phrase = lang('Time.weeks', [abs($weeks)], $locale);
            $before = $days < 0;
        } elseif ($days !== 0) {
            $phrase = lang('Time.days', [abs($days)], $locale);
            $before = $days < 0;
        } elseif ($hours !== 0) {
            $phrase = lang('Time.hours', [abs($hours)], $locale);
            $before = $hours < 0;
        } elseif ($minutes !== 0) {
            $phrase = lang('Time.minutes', [abs($minutes)], $locale);
            $before = $minutes < 0;
        } else {
            return lang('Time.now', [], $locale);
        }
        return $before ? lang('Time.ago', [$phrase], $locale) : lang('Time.inFuture', [$phrase], $locale);
    }
    /**
     * Allow property-like access to our calculated values.
     *
     * @param string $name
     *
     * @return float|int|null
     */
    public function __get($name)
    {
        $name = ucfirst(strtolower($name));
        $method = "get{$name}";
        if (method_exists($this, $method)) {
            return $this->{$method}();
        }
        return null;
    }
    /**
     * Allow property-like checking for our calculated values.
     *
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        $name = ucfirst(strtolower($name));
        $method = "get{$name}";
        return method_exists($this, $method);
    }
}