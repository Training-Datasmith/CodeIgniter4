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
namespace Code_Igniter\Debug\Toolbar\Collectors;

use Code_Igniter\Database\Query;
use Code_Igniter\I18n\Time;
use Config\Toolbar;
/**
 * Collector for the Database tab of the Debug Toolbar.
 *
 * @see \CodeIgniter\Debug\Toolbar\Collectors\DatabaseTest
 */
class Database extends Base_Collector
{
    /**
     * Whether this collector has timeline data.
     *
     * @var bool
     */
    protected $has_timeline = true;
    /**
     * Whether this collector should display its own tab.
     *
     * @var bool
     */
    protected $has_tab_content = true;
    /**
     * Whether this collector has data for the Vars tab.
     *
     * @var bool
     */
    protected $has_var_data = false;
    /**
     * The name used to reference this collector in the toolbar.
     *
     * @var string
     */
    protected $title = 'Database';
    /**
     * Array of database connections.
     *
     * @var array
     */
    protected $connections;
    /**
     * The query instances that have been collected
     * through the DBQuery Event.
     *
     * @var array
     */
    protected static $queries = [];
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->get_connections();
    }
    /**
     * The static method used during Events to collect
     * data.
     *
     * @internal
     *
     * @return void
     */
    public static function collect(Query $query)
    {
        $config = config(Toolbar::class);
        // Provide default in case it's not set
        $max = $config->max_queries ?: 100;
        if (count(static::$queries) < $max) {
            $query_string = $query->get_query();
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            if (!is_cli()) {
                // when called in the browser, the first two trace arrays
                // are from the DB event trigger, which are unneeded
                $backtrace = array_slice($backtrace, 2);
            }
            static::$queries[] = ['query' => $query, 'string' => $query_string, 'duplicate' => in_array($query_string, array_column(static::$queries, 'string'), true), 'trace' => $backtrace];
        }
    }
    /**
     * Returns timeline data formatted for the toolbar.
     *
     * @return array The formatted data or an empty array.
     */
    protected function format_timeline_data(): array
    {
        $data = [];
        foreach ($this->connections as $alias => $connection) {
            // Connection Time
            $data[] = ['name' => 'Connecting to Database: "' . $alias . '"', 'component' => 'Database', 'start' => $connection->get_connect_start(), 'duration' => $connection->get_connect_duration()];
        }
        foreach (static::$queries as $query) {
            $data[] = ['name' => 'Query', 'component' => 'Database', 'start' => $query['query']->get_start_time(true), 'duration' => $query['query']->get_duration(), 'query' => $query['query']->debug_toolbar_display()];
        }
        return $data;
    }
    /**
     * Returns the data of this collector to be formatted in the toolbar
     */
    public function display(): array
    {
        return ['queries' => array_map(static function (array $query): array {
            $is_duplicate = $query['duplicate'] === true;
            $first_non_system_line = '';
            foreach ($query['trace'] as $index => &$line) {
                // simplify file and line
                if (isset($line['file'])) {
                    $line['file'] = clean_path($line['file']) . ':' . $line['line'];
                    unset($line['line']);
                } else {
                    $line['file'] = '[internal function]';
                }
                // find the first trace line that does not originate from `system/`
                if ($first_non_system_line === '' && !str_contains($line['file'], 'SYSTEMPATH')) {
                    $first_non_system_line = $line['file'];
                }
                // simplify function call
                if (isset($line['class'])) {
                    $line['function'] = $line['class'] . $line['type'] . $line['function'];
                    unset($line['class'], $line['type']);
                }
                if (strrpos($line['function'], '{closure}') === false) {
                    $line['function'] .= '()';
                }
                $line['function'] = str_repeat(chr(0xc2) . chr(0xa0), 8) . $line['function'];
                // add index numbering padded with nonbreaking space
                $index_padded = str_pad(sprintf('%d', $index + 1), 3, ' ', STR_PAD_LEFT);
                $index_padded = preg_replace('/\s/', chr(0xc2) . chr(0xa0), $index_padded);
                $line['index'] = $index_padded . str_repeat(chr(0xc2) . chr(0xa0), 4);
            }
            return ['hover' => $is_duplicate ? 'This query was called more than once.' : '', 'class' => $is_duplicate ? 'duplicate' : '', 'duration' => (float) $query['query']->get_duration(5) * 1000 . ' ms', 'sql' => $query['query']->debug_toolbar_display(), 'trace' => $query['trace'], 'trace-file' => $first_non_system_line, 'qid' => md5($query['query'] . Time::now()->format('0.u00 U'))];
        }, static::$queries)];
    }
    /**
     * Gets the "badge" value for the button.
     */
    public function get_badge_value(): int
    {
        return count(static::$queries);
    }
    /**
     * Information to be displayed next to the title.
     *
     * @return string The number of queries (in parentheses) or an empty string.
     */
    public function get_title_details(): string
    {
        $this->get_connections();
        $query_count = count(static::$queries);
        $unique_count = count(array_filter(static::$queries, static fn($query): bool => $query['duplicate'] === false));
        $connection_count = count($this->connections);
        return sprintf('(%d total Quer%s, %d %s unique across %d Connection%s)', $query_count, $query_count > 1 ? 'ies' : 'y', $unique_count, $unique_count > 1 ? 'of them' : '', $connection_count, $connection_count > 1 ? 's' : '');
    }
    /**
     * Does this collector have any data collected?
     */
    public function is_empty(): bool
    {
        return static::$queries === [];
    }
    /**
     * Display the icon.
     *
     * Icon from https://icons8.com - 1em package
     */
    public function icon(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAADMSURBVEhLY6A3YExLSwsA4nIycQDIDIhRWEBqamo/UNF/SjDQjF6ocZgAKPkRiFeEhoYyQ4WIBiA9QAuWAPEHqBAmgLqgHcolGQD1V4DMgHIxwbCxYD+QBqcKINseKo6eWrBioPrtQBq/BcgY5ht0cUIYbBg2AJKkRxCNWkDQgtFUNJwtABr+F6igE8olGQD114HMgHIxAVDyAhA/AlpSA8RYUwoeXAPVex5qHCbIyMgwBCkAuQJIY00huDBUz/mUlBQDqHGjgBjAwAAACexpph6oHSQAAAAASUVORK5CYII=';
    }
    /**
     * Gets the connections from the database config
     */
    private function get_connections(): void
    {
        $this->connections = \Config\Database::get_connections();
    }
    /**
     * Reset collector state for worker mode.
     * Clears collected queries between requests.
     */
    public function reset(): void
    {
        static::$queries = [];
    }
}