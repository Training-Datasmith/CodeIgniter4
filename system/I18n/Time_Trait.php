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

use Code_Igniter\I18n\Exceptions\I18n_Exception;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Intl_Calendar;
use Intl_Date_Formatter;
use Locale;
/**
 * This trait has properties and methods for Time and TimeLegacy.
 * When TimeLegacy is removed, this will be in Time.
 */
trait Time_Trait
{
    /**
     * @var DateTimeZone|string
     */
    protected $timezone;
    /**
     * @var string
     */
    protected $locale;
    /**
     * Format to use when displaying datetime through __toString
     *
     * @var string
     */
    protected $to_string_format = 'yyyy-MM-dd HH:mm:ss';
    /**
     * Used to check time string to determine if it is relative time or not....
     *
     * @var string
     */
    protected static $relative_pattern = '/this|next|last|tomorrow|yesterday|midnight|today|[+-]|first|last|ago/i';
    /**
     * @var DateTimeInterface|static|null
     */
    protected static $test_now;
    // --------------------------------------------------------------------
    // Constructors
    // --------------------------------------------------------------------
    /**
     * Time constructor.
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @throws Exception
     */
    public function __construct(?string $time = null, $timezone = null, ?string $locale = null)
    {
        $this->locale = in_array($locale, [null, '', '0'], true) ? Locale::get_default() : $locale;
        $time ??= '';
        // If a test instance has been provided, use it instead.
        if ($time === '' && static::$test_now instanceof static) {
            if ($timezone !== null) {
                $test_now = static::$test_now->set_timezone($timezone);
                $time = $test_now->format('Y-m-d H:i:s.u');
            } else {
                $timezone = static::$test_now->get_timezone();
                $time = static::$test_now->format('Y-m-d H:i:s.u');
            }
        }
        $timezone = $timezone ?: date_default_timezone_get();
        $this->timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
        // If the time string was a relative string (i.e. 'next Tuesday')
        // then we need to adjust the time going in so that we have a current
        // timezone to work with.
        if ($time !== '' && static::has_relative_keywords($time)) {
            $instance = new DateTime('now', $this->timezone);
            $instance->modify($time);
            $time = $instance->format('Y-m-d H:i:s.u');
        }
        parent::__construct($time, $this->timezone);
    }
    /**
     * Returns a new Time instance with the timezone set.
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @return static
     *
     * @throws Exception
     */
    public static function now($timezone = null, ?string $locale = null)
    {
        return new static(null, $timezone, $locale);
    }
    /**
     * Returns a new Time instance while parsing a datetime string.
     *
     * Example:
     *  $time = Time::parse('first day of December 2008');
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @return static
     *
     * @throws Exception
     */
    public static function parse(string $datetime, $timezone = null, ?string $locale = null)
    {
        return new static($datetime, $timezone, $locale);
    }
    /**
     * Return a new time with the time set to midnight.
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @return static
     *
     * @throws Exception
     */
    public static function today($timezone = null, ?string $locale = null)
    {
        return new static(date('Y-m-d 00:00:00'), $timezone, $locale);
    }
    /**
     * Returns an instance set to midnight yesterday morning.
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @return static
     *
     * @throws Exception
     */
    public static function yesterday($timezone = null, ?string $locale = null)
    {
        return new static(date('Y-m-d 00:00:00', strtotime('-1 day')), $timezone, $locale);
    }
    /**
     * Returns an instance set to midnight tomorrow morning.
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @return static
     *
     * @throws Exception
     */
    public static function tomorrow($timezone = null, ?string $locale = null)
    {
        return new static(date('Y-m-d 00:00:00', strtotime('+1 day')), $timezone, $locale);
    }
    /**
     * Returns a new instance based on the year, month and day. If any of those three
     * are left empty, will default to the current value.
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @return static
     *
     * @throws Exception
     */
    public static function create_from_date(?int $year = null, ?int $month = null, ?int $day = null, $timezone = null, ?string $locale = null)
    {
        return static::create($year, $month, $day, null, null, null, $timezone, $locale);
    }
    /**
     * Returns a new instance with the date set to today, and the time set to the values passed in.
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @return static
     *
     * @throws Exception
     */
    public static function create_from_time(?int $hour = null, ?int $minutes = null, ?int $seconds = null, $timezone = null, ?string $locale = null)
    {
        return static::create(null, null, null, $hour, $minutes, $seconds, $timezone, $locale);
    }
    /**
     * Returns a new instance with the date time values individually set.
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @return static
     *
     * @throws Exception
     */
    public static function create(?int $year = null, ?int $month = null, ?int $day = null, ?int $hour = null, ?int $minutes = null, ?int $seconds = null, $timezone = null, ?string $locale = null)
    {
        $year ??= date('Y');
        $month ??= date('m');
        $day ??= date('d');
        $hour ??= 0;
        $minutes ??= 0;
        $seconds ??= 0;
        return new static(date('Y-m-d H:i:s', strtotime("{$year}-{$month}-{$day} {$hour}:{$minutes}:{$seconds}")), $timezone, $locale);
    }
    /**
     * Provides a replacement for DateTime's own createFromFormat function, that provides
     * more flexible timeZone handling
     *
     * @psalm-external-mutation-free
     *
     * @param string                   $format
     * @param string                   $datetime
     * @param DateTimeZone|string|null $timezone
     *
     * @throws Exception
     */
    public static function create_from_format($format, $datetime, $timezone = null): static
    {
        if (!$date = parent::create_from_format($format, $datetime)) {
            throw I18n_Exception::for_invalid_format($format);
        }
        return new static($date->format('Y-m-d H:i:s.u'), $timezone);
    }
    /**
     * Returns a new instance with the datetime set based on the provided UNIX timestamp.
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @throws Exception
     */
    public static function create_from_timestamp(float|int $timestamp, $timezone = null, ?string $locale = null): static
    {
        $time = new static(sprintf('@%.6f', $timestamp), 'UTC', $locale);
        $timezone ??= 'UTC';
        return $time->set_timezone($timezone);
    }
    /**
     * Takes an instance of DateTimeInterface and returns an instance of Time with it's same values.
     *
     * @return static
     *
     * @throws Exception
     */
    public static function create_from_instance(DateTimeInterface $date_time, ?string $locale = null)
    {
        $date = $date_time->format('Y-m-d H:i:s.u');
        $timezone = $date_time->get_timezone();
        return new static($date, $timezone, $locale);
    }
    /**
     * Takes an instance of DateTime and returns an instance of Time with it's same values.
     *
     * @return static
     *
     * @throws Exception
     *
     * @deprecated         Use createFromInstance() instead
     *
     * @codeCoverageIgnore
     */
    public static function instance(DateTime $date_time, ?string $locale = null)
    {
        return static::create_from_instance($date_time, $locale);
    }
    /**
     * Converts the current instance to a mutable DateTime object.
     *
     * @return DateTime
     *
     * @throws Exception
     */
    public function to_date_time()
    {
        return DateTime::create_from_format('Y-m-d H:i:s.u', $this->format('Y-m-d H:i:s.u'), $this->get_timezone());
    }
    // --------------------------------------------------------------------
    // For Testing
    // --------------------------------------------------------------------
    /**
     * Creates an instance of Time that will be returned during testing
     * when calling 'Time::now()' instead of the current time.
     *
     * @param DateTimeInterface|self|string|null $datetime
     * @param DateTimeZone|string|null           $timezone
     *
     * @return void
     *
     * @throws Exception
     */
    public static function set_test_now($datetime = null, $timezone = null, ?string $locale = null)
    {
        // Reset the test instance
        if ($datetime === null) {
            static::$test_now = null;
            return;
        }
        // Convert to a Time instance
        if (is_string($datetime)) {
            $datetime = new static($datetime, $timezone, $locale);
        } elseif ($datetime instanceof DateTimeInterface && !$datetime instanceof static) {
            $datetime = new static($datetime->format('Y-m-d H:i:s.u'), $timezone);
        }
        static::$test_now = $datetime;
    }
    /**
     * Returns whether we have a testNow instance saved.
     */
    public static function has_test_now(): bool
    {
        return static::$test_now !== null;
    }
    // --------------------------------------------------------------------
    // Getters
    // --------------------------------------------------------------------
    /**
     * Returns the localized Year
     *
     * @throws Exception
     */
    public function get_year(): string
    {
        return $this->to_localized_string('y');
    }
    /**
     * Returns the localized Month
     *
     * @throws Exception
     */
    public function get_month(): string
    {
        return $this->to_localized_string('M');
    }
    /**
     * Return the localized day of the month.
     *
     * @throws Exception
     */
    public function get_day(): string
    {
        return $this->to_localized_string('d');
    }
    /**
     * Return the localized hour (in 24-hour format).
     *
     * @throws Exception
     */
    public function get_hour(): string
    {
        return $this->to_localized_string('H');
    }
    /**
     * Return the localized minutes in the hour.
     *
     * @throws Exception
     */
    public function get_minute(): string
    {
        return $this->to_localized_string('m');
    }
    /**
     * Return the localized seconds
     *
     * @throws Exception
     */
    public function get_second(): string
    {
        return $this->to_localized_string('s');
    }
    /**
     * Return the index of the day of the week
     *
     * @throws Exception
     */
    public function get_day_of_week(): string
    {
        return $this->to_localized_string('c');
    }
    /**
     * Return the index of the day of the year
     *
     * @throws Exception
     */
    public function get_day_of_year(): string
    {
        return $this->to_localized_string('D');
    }
    /**
     * Return the index of the week in the month
     *
     * @throws Exception
     */
    public function get_week_of_month(): string
    {
        return $this->to_localized_string('W');
    }
    /**
     * Return the index of the week in the year
     *
     * @throws Exception
     */
    public function get_week_of_year(): string
    {
        return $this->to_localized_string('w');
    }
    /**
     * Returns the age in years from the date and 'now'
     *
     * @return int
     *
     * @throws Exception
     */
    public function get_age()
    {
        // future dates have no age
        return max(0, $this->difference(static::now())->get_years());
    }
    /**
     * Returns the number of the current quarter for the year.
     *
     * @throws Exception
     */
    public function get_quarter(): string
    {
        return $this->to_localized_string('Q');
    }
    /**
     * Are we in daylight savings time currently?
     */
    public function get_dst(): bool
    {
        return $this->format('I') === '1';
        // 1 if Daylight Saving Time, 0 otherwise.
    }
    /**
     * Returns boolean whether the passed timezone is the same as
     * the local timezone.
     */
    public function get_local(): bool
    {
        $local = date_default_timezone_get();
        return $local === $this->timezone->get_name();
    }
    /**
     * Returns boolean whether object is in UTC.
     */
    public function get_utc(): bool
    {
        return $this->get_offset() === 0;
    }
    /**
     * Returns the name of the current timezone.
     */
    public function get_timezone_name(): string
    {
        return $this->timezone->get_name();
    }
    // --------------------------------------------------------------------
    // Setters
    // --------------------------------------------------------------------
    /**
     * Sets the current year for this instance.
     *
     * @param int|string $value
     *
     * @return static
     *
     * @throws Exception
     */
    public function set_year($value)
    {
        return $this->set_value('year', $value);
    }
    /**
     * Sets the month of the year.
     *
     * @param int|string $value
     *
     * @return static
     *
     * @throws Exception
     */
    public function set_month($value)
    {
        if (is_numeric($value) && ($value < 1 || $value > 12)) {
            throw I18n_Exception::for_invalid_month((string) $value);
        }
        if (is_string($value) && !is_numeric($value)) {
            $value = date('m', strtotime("{$value} 1 2017"));
        }
        return $this->set_value('month', $value);
    }
    /**
     * Sets the day of the month.
     *
     * @param int|string $value
     *
     * @return static
     *
     * @throws Exception
     */
    public function set_day($value)
    {
        if ($value < 1 || $value > 31) {
            throw I18n_Exception::for_invalid_day((string) $value);
        }
        $date = $this->get_year() . '-' . $this->get_month();
        $last_day = date('t', strtotime($date));
        if ($value > $last_day) {
            throw I18n_Exception::for_invalid_over_day($last_day, (string) $value);
        }
        return $this->set_value('day', $value);
    }
    /**
     * Sets the hour of the day (24 hour cycle)
     *
     * @param int|string $value
     *
     * @return static
     *
     * @throws Exception
     */
    public function set_hour($value)
    {
        if ($value < 0 || $value > 23) {
            throw I18n_Exception::for_invalid_hour((string) $value);
        }
        return $this->set_value('hour', $value);
    }
    /**
     * Sets the minute of the hour
     *
     * @param int|string $value
     *
     * @return static
     *
     * @throws Exception
     */
    public function set_minute($value)
    {
        if ($value < 0 || $value > 59) {
            throw I18n_Exception::for_invalid_minutes((string) $value);
        }
        return $this->set_value('minute', $value);
    }
    /**
     * Sets the second of the minute.
     *
     * @param int|string $value
     *
     * @return static
     *
     * @throws Exception
     */
    public function set_second($value)
    {
        if ($value < 0 || $value > 59) {
            throw I18n_Exception::for_invalid_seconds((string) $value);
        }
        return $this->set_value('second', $value);
    }
    /**
     * Helper method to do the heavy lifting of the 'setX' methods.
     *
     * @param int $value
     *
     * @return static
     *
     * @throws Exception
     */
    protected function set_value(string $name, $value)
    {
        [$year, $month, $day, $hour, $minute, $second] = explode('-', $this->format('Y-n-j-G-i-s'));
        ${$name} = $value;
        return static::create((int) $year, (int) $month, (int) $day, (int) $hour, (int) $minute, (int) $second, $this->get_timezone_name(), $this->locale);
    }
    /**
     * Returns a new instance with the revised timezone.
     *
     * @param DateTimeZone|string $timezone
     *
     * @throws Exception
     */
    public function set_timezone($timezone): static
    {
        $timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
        $date_time = $this->to_date_time()->set_timezone($timezone);
        return static::create_from_instance($date_time, $this->locale);
    }
    // --------------------------------------------------------------------
    // Add/Subtract
    // --------------------------------------------------------------------
    /**
     * Returns a new Time instance with $seconds added to the time.
     *
     * @return static
     */
    public function add_seconds(int $seconds)
    {
        $time = clone $this;
        return $time->add(DateInterval::create_from_date_string("{$seconds} seconds"));
    }
    /**
     * Returns a new Time instance with $minutes added to the time.
     *
     * @return static
     */
    public function add_minutes(int $minutes)
    {
        $time = clone $this;
        return $time->add(DateInterval::create_from_date_string("{$minutes} minutes"));
    }
    /**
     * Returns a new Time instance with $hours added to the time.
     *
     * @return static
     */
    public function add_hours(int $hours)
    {
        $time = clone $this;
        return $time->add(DateInterval::create_from_date_string("{$hours} hours"));
    }
    /**
     * Returns a new Time instance with $days added to the time.
     *
     * @return static
     */
    public function add_days(int $days)
    {
        $time = clone $this;
        return $time->add(DateInterval::create_from_date_string("{$days} days"));
    }
    /**
     * Returns a new Time instance with $months added to the time.
     *
     * @return static
     */
    public function add_months(int $months)
    {
        $time = clone $this;
        return $time->add(DateInterval::create_from_date_string("{$months} months"));
    }
    /**
     * Returns a new Time instance with $months calendar months added to the time.
     */
    public function add_calendar_months(int $months): static
    {
        $time = clone $this;
        $year = (int) $time->get_year();
        $month = (int) $time->get_month();
        $day = (int) $time->get_day();
        // Adjust total months since year 0
        $total_months = $year * 12 + $month - 1 + $months;
        // Recalculate year and month
        $new_year = intdiv($total_months, 12);
        $new_month = $total_months % 12 + 1;
        // Get last day of new month
        $last_day_of_month = cal_days_in_month(CAL_GREGORIAN, $new_month, $new_year);
        $corrected_day = min($day, $last_day_of_month);
        return static::create($new_year, $new_month, $corrected_day, (int) $this->get_hour(), (int) $this->get_minute(), (int) $this->get_second(), $this->get_timezone(), $this->locale);
    }
    /**
     * Returns a new Time instance with $months calendar months subtracted from the time
     */
    public function sub_calendar_months(int $months): static
    {
        return $this->add_calendar_months(-$months);
    }
    /**
     * Returns a new Time instance with $years added to the time.
     *
     * @return static
     */
    public function add_years(int $years)
    {
        $time = clone $this;
        return $time->add(DateInterval::create_from_date_string("{$years} years"));
    }
    /**
     * Returns a new Time instance with $seconds subtracted from the time.
     *
     * @return static
     */
    public function sub_seconds(int $seconds)
    {
        $time = clone $this;
        return $time->sub(DateInterval::create_from_date_string("{$seconds} seconds"));
    }
    /**
     * Returns a new Time instance with $minutes subtracted from the time.
     *
     * @return static
     */
    public function sub_minutes(int $minutes)
    {
        $time = clone $this;
        return $time->sub(DateInterval::create_from_date_string("{$minutes} minutes"));
    }
    /**
     * Returns a new Time instance with $hours subtracted from the time.
     *
     * @return static
     */
    public function sub_hours(int $hours)
    {
        $time = clone $this;
        return $time->sub(DateInterval::create_from_date_string("{$hours} hours"));
    }
    /**
     * Returns a new Time instance with $days subtracted from the time.
     *
     * @return static
     */
    public function sub_days(int $days)
    {
        $time = clone $this;
        return $time->sub(DateInterval::create_from_date_string("{$days} days"));
    }
    /**
     * Returns a new Time instance with $months subtracted from the time.
     *
     * @return static
     */
    public function sub_months(int $months)
    {
        $time = clone $this;
        return $time->sub(DateInterval::create_from_date_string("{$months} months"));
    }
    /**
     * Returns a new Time instance with $hours subtracted from the time.
     *
     * @return static
     */
    public function sub_years(int $years)
    {
        $time = clone $this;
        return $time->sub(DateInterval::create_from_date_string("{$years} years"));
    }
    // --------------------------------------------------------------------
    // Formatters
    // --------------------------------------------------------------------
    /**
     * Returns the localized value of the date in the format 'Y-m-d H:i:s'
     *
     * @return false|string
     *
     * @throws Exception
     */
    public function to_date_time_string()
    {
        return $this->to_localized_string('yyyy-MM-dd HH:mm:ss');
    }
    /**
     * Returns a localized version of the date in Y-m-d format.
     *
     * @return string
     *
     * @throws Exception
     */
    public function to_date_string()
    {
        return $this->to_localized_string('yyyy-MM-dd');
    }
    /**
     * Returns a localized version of the date in nicer date format:
     *
     *  i.e. Apr 1, 2017
     *
     * @return string
     *
     * @throws Exception
     */
    public function to_formatted_date_string()
    {
        return $this->to_localized_string('MMM d, yyyy');
    }
    /**
     * Returns a localized version of the time in nicer date format:
     *
     *  i.e. 13:20:33
     *
     * @return string
     *
     * @throws Exception
     */
    public function to_time_string()
    {
        return $this->to_localized_string('HH:mm:ss');
    }
    /**
     * Returns the localized value of this instance in $format.
     *
     * @return false|string
     *
     * @throws Exception
     */
    public function to_localized_string(?string $format = null)
    {
        $format ??= $this->to_string_format;
        return Intl_Date_Formatter::format_object($this->to_date_time(), $format, $this->locale);
    }
    // --------------------------------------------------------------------
    // Comparison
    // --------------------------------------------------------------------
    /**
     * Determines if the datetime passed in is equal to the current instance.
     * Equal in this case means that they represent the same moment in time,
     * and are not required to be in the same timezone, as both times are
     * converted to UTC and compared that way.
     *
     * @param DateTimeInterface|self|string $testTime
     *
     * @throws Exception
     */
    public function equals($test_time, ?string $timezone = null): bool
    {
        $test_time = $this->get_utc_object($test_time, $timezone);
        $our_time = $this->to_date_time()->set_timezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        return $test_time->format('Y-m-d H:i:s.u') === $our_time;
    }
    /**
     * Ensures that the times are identical, taking timezone into account.
     *
     * @param DateTimeInterface|self|string $testTime
     *
     * @throws Exception
     */
    public function same_as($test_time, ?string $timezone = null): bool
    {
        if ($test_time instanceof DateTimeInterface) {
            $test_time = $test_time->format('Y-m-d H:i:s.u O');
        } elseif (is_string($test_time)) {
            $timezone = in_array($timezone, [null, '', '0'], true) ? $this->timezone : $timezone;
            $timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
            $test_time = new DateTime($test_time, $timezone);
            $test_time = $test_time->format('Y-m-d H:i:s.u O');
        }
        $our_time = $this->format('Y-m-d H:i:s.u O');
        return $test_time === $our_time;
    }
    /**
     * Determines if the current instance's time is before $testTime,
     * after converting to UTC.
     *
     * @param DateTimeInterface|self|string $testTime
     *
     * @throws Exception
     */
    public function is_before($test_time, ?string $timezone = null): bool
    {
        $test_time = $this->get_utc_object($test_time, $timezone);
        $test_timestamp = $test_time->get_timestamp();
        $our_timestamp = $this->get_timestamp();
        if ($our_timestamp === $test_timestamp) {
            return $this->format('u') < $test_time->format('u');
        }
        return $our_timestamp < $test_timestamp;
    }
    /**
     * Determines if the current instance's time is after $testTime,
     * after converting in UTC.
     *
     * @param DateTimeInterface|self|string $testTime
     *
     * @throws Exception
     */
    public function is_after($test_time, ?string $timezone = null): bool
    {
        $test_time = $this->get_utc_object($test_time, $timezone);
        $test_timestamp = $test_time->get_timestamp();
        $our_timestamp = $this->get_timestamp();
        if ($our_timestamp === $test_timestamp) {
            return $this->format('u') > $test_time->format('u');
        }
        return $our_timestamp > $test_timestamp;
    }
    /**
     * Determines if the current instance's time is in the past.
     *
     * @throws Exception
     */
    public function is_past(): bool
    {
        return $this->is_before(static::now($this->timezone));
    }
    /**
     * Determines if the current instance's time is in the future.
     *
     * @throws Exception
     */
    public function is_future(): bool
    {
        return $this->is_after(static::now($this->timezone));
    }
    // --------------------------------------------------------------------
    // Differences
    // --------------------------------------------------------------------
    /**
     * Returns a text string that is easily readable that describes
     * how long ago, or how long from now, a date is, like:
     *
     *  - 3 weeks ago
     *  - in 4 days
     *  - 6 hours ago
     *
     * @return string
     *
     * @throws Exception
     */
    public function humanize()
    {
        $now = Intl_Calendar::from_date_time(self::now($this->timezone)->to_date_time());
        $time = $this->get_calendar()->get_time();
        $years = $now->field_difference($time, Intl_Calendar::FIELD_YEAR);
        $months = $now->field_difference($time, Intl_Calendar::FIELD_MONTH);
        $days = $now->field_difference($time, Intl_Calendar::FIELD_DAY_OF_YEAR);
        $hours = $now->field_difference($time, Intl_Calendar::FIELD_HOUR_OF_DAY);
        $minutes = $now->field_difference($time, Intl_Calendar::FIELD_MINUTE);
        $phrase = null;
        if ($years !== 0) {
            $phrase = lang('Time.years', [abs($years)]);
            $before = $years < 0;
        } elseif ($months !== 0) {
            $phrase = lang('Time.months', [abs($months)]);
            $before = $months < 0;
        } elseif ($days !== 0 && abs($days) >= 7) {
            $weeks = ceil($days / 7);
            $phrase = lang('Time.weeks', [abs($weeks)]);
            $before = $days < 0;
        } elseif ($days !== 0) {
            $before = $days < 0;
            // Yesterday/Tomorrow special cases
            if (abs($days) === 1) {
                return $before ? lang('Time.yesterday') : lang('Time.tomorrow');
            }
            $phrase = lang('Time.days', [abs($days)]);
        } elseif ($hours !== 0) {
            $phrase = lang('Time.hours', [abs($hours)]);
            $before = $hours < 0;
        } elseif ($minutes !== 0) {
            $phrase = lang('Time.minutes', [abs($minutes)]);
            $before = $minutes < 0;
        } else {
            return lang('Time.now');
        }
        return $before ? lang('Time.ago', [$phrase]) : lang('Time.inFuture', [$phrase]);
    }
    /**
     * @param DateTimeInterface|self|string $testTime
     *
     * @return TimeDifference
     *
     * @throws Exception
     */
    public function difference($test_time, ?string $timezone = null)
    {
        if (is_string($test_time)) {
            $timezone = $timezone !== null ? new DateTimeZone($timezone) : $this->timezone;
            $test_time = new DateTime($test_time, $timezone);
        } elseif ($test_time instanceof static) {
            $test_time = $test_time->to_date_time();
        }
        assert($test_time instanceof DateTime);
        if ($this->timezone->get_offset($this) !== $test_time->get_timezone()->get_offset($this)) {
            $test_time = $this->get_utc_object($test_time, $timezone);
            $our_time = $this->get_utc_object($this);
        } else {
            $our_time = $this->to_date_time();
        }
        return new Time_Difference($our_time, $test_time);
    }
    // --------------------------------------------------------------------
    // Utilities
    // --------------------------------------------------------------------
    /**
     * Returns a Time instance with the timezone converted to UTC.
     *
     * @param DateTimeInterface|self|string $time
     *
     * @return DateTime|static
     *
     * @throws Exception
     */
    public function get_utc_object($time, ?string $timezone = null)
    {
        if ($time instanceof static) {
            $time = $time->to_date_time();
        } elseif (is_string($time)) {
            $timezone = in_array($timezone, [null, '', '0'], true) ? $this->timezone : $timezone;
            $timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
            $time = new DateTime($time, $timezone);
        }
        if ($time instanceof DateTime || $time instanceof DateTimeImmutable) {
            $time = $time->set_timezone(new DateTimeZone('UTC'));
        }
        return $time;
    }
    /**
     * Returns the IntlCalendar object used for this object,
     * taking into account the locale, date, etc.
     *
     * Primarily used internally to provide the difference and comparison functions,
     * but available for public consumption if they need it.
     *
     * @return IntlCalendar
     *
     * @throws Exception
     */
    public function get_calendar()
    {
        return Intl_Calendar::from_date_time($this->to_date_time());
    }
    /**
     * Check a time string to see if it includes a relative date (like 'next Tuesday').
     */
    protected static function has_relative_keywords(string $time): bool
    {
        // skip common format with a '-' in it
        if (preg_match('/\d{4}-\d{1,2}-\d{1,2}/', $time) !== 1) {
            return preg_match(static::$relative_pattern, $time) > 0;
        }
        return false;
    }
    /**
     * Outputs a short format version of the datetime.
     * The output is NOT localized intentionally.
     */
    public function __toString(): string
    {
        return $this->format('Y-m-d H:i:s');
    }
    /**
     * Allow for property-type access to any getX method...
     *
     * Note that we cannot use this for any of our setX methods,
     * as they return new Time objects, but the __set ignores
     * return values.
     * See http://php.net/manual/en/language.oop5.overloading.php
     *
     * @param string $name
     *
     * @return array|bool|DateTimeInterface|DateTimeZone|int|IntlCalendar|self|string|null
     */
    public function __get($name)
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->{$method}();
        }
        return null;
    }
    /**
     * Allow for property-type checking to any getX method...
     *
     * @param string $name
     */
    public function __isset($name): bool
    {
        $method = 'get' . ucfirst($name);
        return method_exists($this, $method);
    }
    /**
     * This is called when we unserialize the Time object.
     *
     * @param array{date: string, timezone: string, timezone_type: int} $data
     */
    public function __unserialize(array $data): void
    {
        parent::__construct($data['date'], new DateTimeZone($data['timezone']));
    }
}