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
namespace Code_Igniter\Database;

use Stringable;
/**
 * Query builder
 */
class Query implements Query_Interface, Stringable
{
    /**
     * The query string, as provided by the user.
     *
     * @var string
     */
    protected $original_query_string;
    /**
     * The query string if table prefix has been swapped.
     *
     * @var string|null
     */
    protected $swapped_query_string;
    /**
     * The final query string after binding, etc.
     *
     * @var string|null
     */
    protected $final_query_string;
    /**
     * The binds and their values used for binding.
     *
     * @var array
     */
    protected $binds = [];
    /**
     * Bind marker
     *
     * Character used to identify values in a prepared statement.
     *
     * @var string
     */
    protected $bind_marker = '?';
    /**
     * The start time in seconds with microseconds
     * for when this query was executed.
     *
     * @var float|string
     */
    protected $start_time;
    /**
     * The end time in seconds with microseconds
     * for when this query was executed.
     *
     * @var float
     */
    protected $end_time;
    /**
     * The error code, if any.
     *
     * @var int
     */
    protected $error_code;
    /**
     * The error message, if any.
     *
     * @var string
     */
    protected $error_string;
    /**
     * Pointer to database connection.
     * Mainly for escaping features.
     *
     * @var ConnectionInterface
     */
    public $db;
    public function __construct(Connection_Interface $db)
    {
        $this->db = $db;
    }
    public function set_query(string $sql, mixed $binds = null, bool $set_escape = true): self
    {
        $this->original_query_string = $sql;
        unset($this->swapped_query_string);
        if ($binds !== null) {
            if (!is_array($binds)) {
                $binds = [$binds];
            }
            if ($set_escape) {
                array_walk($binds, static function (&$item): void {
                    $item = [$item, true];
                });
            }
            $this->binds = $binds;
        }
        unset($this->final_query_string);
        return $this;
    }
    /**
     * Will store the variables to bind into the query later.
     *
     * @return $this
     */
    public function set_binds(array $binds, bool $set_escape = true)
    {
        if ($set_escape) {
            array_walk($binds, static function (&$item): void {
                $item = [$item, true];
            });
        }
        $this->binds = $binds;
        unset($this->final_query_string);
        return $this;
    }
    public function get_query(): string
    {
        if (empty($this->final_query_string)) {
            $this->compile_binds();
        }
        return $this->final_query_string;
    }
    public function set_duration(float $start, ?float $end = null): self
    {
        $this->start_time = $start;
        if ($end === null) {
            $end = microtime(true);
        }
        $this->end_time = $end;
        return $this;
    }
    /**
     * Returns the start time in seconds with microseconds.
     *
     * @return float|string
     */
    public function get_start_time(bool $return_raw = false, int $decimals = 6)
    {
        if ($return_raw) {
            return $this->start_time;
        }
        return number_format($this->start_time, $decimals);
    }
    public function get_duration(int $decimals = 6): string
    {
        return number_format($this->end_time - $this->start_time, $decimals);
    }
    public function set_error(int $code, string $error): self
    {
        $this->error_code = $code;
        $this->error_string = $error;
        return $this;
    }
    public function has_error(): bool
    {
        return !empty($this->error_string);
    }
    public function get_error_code(): int
    {
        return $this->error_code;
    }
    public function get_error_message(): string
    {
        return $this->error_string;
    }
    public function is_write_type(): bool
    {
        return $this->db->is_write_type($this->original_query_string);
    }
    public function swap_prefix(string $orig, string $swap): self
    {
        $sql = $this->swapped_query_string ?? $this->original_query_string;
        $from = '/(\W)' . $orig . '(\S)/';
        $to = '\1' . $swap . '\2';
        $this->swapped_query_string = preg_replace($from, $to, $sql);
        unset($this->final_query_string);
        return $this;
    }
    public function get_original_query(): string
    {
        return $this->original_query_string;
    }
    /**
     * Escapes and inserts any binds into the finalQueryString property.
     *
     * @see https://regex101.com/r/EUEhay/5
     *
     * @return void
     */
    protected function compile_binds()
    {
        $sql = $this->swapped_query_string ?? $this->original_query_string;
        $binds = $this->binds;
        if (empty($binds)) {
            $this->final_query_string = $sql;
            return;
        }
        if (is_int(array_key_first($binds))) {
            $bind_count = count($binds);
            $ml = strlen($this->bind_marker);
            $this->final_query_string = $this->match_simple_binds($sql, $binds, $bind_count, $ml);
        } else {
            // Reverse the binds so that duplicate named binds
            // will be processed prior to the original binds.
            $binds = array_reverse($binds);
            $this->final_query_string = $this->match_named_binds($sql, $binds);
        }
    }
    protected function match_named_binds(string $sql, array $binds): string
    {
        $replacers = [];
        foreach ($binds as $placeholder => $value) {
            // $value[1] contains the boolean whether should be escaped or not
            $escaped_value = $value[1] ? $this->db->escape($value[0]) : $value[0];
            // In order to correctly handle backlashes in saved strings
            // we will need to preg_quote, so remove the wrapping escape characters
            // otherwise it will get escaped.
            if (is_array($value[0])) {
                $escaped_value = '(' . implode(',', $escaped_value) . ')';
            }
            $replacers[":{$placeholder}:"] = $escaped_value;
        }
        return strtr($sql, $replacers);
    }
    protected function match_simple_binds(string $sql, array $binds, int $bind_count, int $ml): string
    {
        if ($c = preg_match_all("/'[^']*'/", $sql, $matches) >= 1) {
            $c = preg_match_all('/' . preg_quote($this->bind_marker, '/') . '/i', str_replace($matches[0], str_replace($this->bind_marker, str_repeat(' ', $ml), $matches[0]), $sql, $c), $matches, PREG_OFFSET_CAPTURE);
            // Bind values' count must match the count of markers in the query
            if ($bind_count !== $c) {
                return $sql;
            }
        } elseif (($c = preg_match_all('/' . preg_quote($this->bind_marker, '/') . '/i', $sql, $matches, PREG_OFFSET_CAPTURE)) !== $bind_count) {
            return $sql;
        }
        do {
            $c--;
            $escaped_value = $binds[$c][1] ? $this->db->escape($binds[$c][0]) : $binds[$c][0];
            if (is_array($escaped_value)) {
                $escaped_value = '(' . implode(',', $escaped_value) . ')';
            }
            $sql = substr_replace($sql, (string) $escaped_value, $matches[0][$c][1], $ml);
        } while ($c !== 0);
        return $sql;
    }
    /**
     * Returns string to display in debug toolbar
     */
    public function debug_toolbar_display(): string
    {
        // Key words we want bolded
        static $highlight = ['AND', 'AS', 'ASC', 'AVG', 'BY', 'COUNT', 'DESC', 'DISTINCT', 'FROM', 'GROUP', 'HAVING', 'IN', 'INNER', 'INSERT', 'INTO', 'IS', 'JOIN', 'LEFT', 'LIKE', 'LIMIT', 'MAX', 'MIN', 'NOT', 'NULL', 'OFFSET', 'ON', 'OR', 'ORDER', 'RIGHT', 'SELECT', 'SUM', 'UPDATE', 'VALUES', 'WHERE'];
        $sql = esc($this->get_query());
        /**
         * @see https://stackoverflow.com/a/20767160
         * @see https://regex101.com/r/hUlrGN/4
         */
        $search = '/\b(?:' . implode('|', $highlight) . ')\b(?![^(&#039;)]*&#039;(?:(?:[^(&#039;)]*&#039;){2})*[^(&#039;)]*$)/';
        return preg_replace_callback($search, static fn($matches): string => '<strong>' . str_replace(' ', '&nbsp;', $matches[0]) . '</strong>', $sql);
    }
    /**
     * Return text representation of the query
     */
    public function __toString(): string
    {
        return $this->get_query();
    }
}