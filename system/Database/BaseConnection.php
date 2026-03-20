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

use Closure;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Events\Events;
use stdClass;
use Stringable;
use Throwable;
/**
 * @property-read array      $aliasedTables
 * @property-read string     $charset
 * @property-read bool       $compress
 * @property-read float      $connectDuration
 * @property-read float      $connectTime
 * @property-read string     $database
 * @property-read array      $dateFormat
 * @property-read string     $DBCollat
 * @property-read bool       $DBDebug
 * @property-read string     $DBDriver
 * @property-read string     $DBPrefix
 * @property-read string     $DSN
 * @property-read array|bool $encrypt
 * @property-read array      $failover
 * @property-read string     $hostname
 * @property-read Query      $lastQuery
 * @property-read string     $password
 * @property-read bool       $pConnect
 * @property-read int|string $port
 * @property-read bool       $pretend
 * @property-read string     $queryClass
 * @property-read array      $reservedIdentifiers
 * @property-read bool       $strictOn
 * @property-read string     $subdriver
 * @property-read string     $swapPre
 * @property-read int        $transDepth
 * @property-read bool       $transFailure
 * @property-read bool       $transStatus
 * @property-read string     $username
 *
 * @template TConnection
 * @template TResult
 *
 * @implements ConnectionInterface<TConnection, TResult>
 * @see \CodeIgniter\Database\BaseConnectionTest
 */
abstract class Base_Connection implements Connection_Interface
{
    /**
     * Data Source Name / Connect string
     *
     * @var string
     */
    protected $DSN;
    /**
     * Database port
     *
     * @var int|string
     */
    protected $port = '';
    /**
     * Hostname
     *
     * @var string
     */
    protected $hostname;
    /**
     * Username
     *
     * @var string
     */
    protected $username;
    /**
     * Password
     *
     * @var string
     */
    protected $password;
    /**
     * Database name
     *
     * @var string
     */
    protected $database;
    /**
     * Database driver
     *
     * @var string
     */
    protected $db_driver = 'MySQLi';
    /**
     * Sub-driver
     *
     * @used-by CI_DB_pdo_driver
     *
     * @var string
     */
    protected $subdriver;
    /**
     * Table prefix
     *
     * @var string
     */
    protected $db_prefix = '';
    /**
     * Persistent connection flag
     *
     * @var bool
     */
    protected $p_connect = false;
    /**
     * Whether to throw Exception or not when an error occurs.
     *
     * @var bool
     */
    protected $db_debug = true;
    /**
     * Character set
     *
     * This value must be updated by Config\Database if the driver use it.
     *
     * @var string
     */
    protected $charset = '';
    /**
     * Collation
     *
     * This value must be updated by Config\Database if the driver use it.
     *
     * @var string
     */
    protected $db_collat = '';
    /**
     * Swap Prefix
     *
     * @var string
     */
    protected $swap_pre = '';
    /**
     * Encryption flag/data
     *
     * @var array|bool
     */
    protected $encrypt = false;
    /**
     * Compression flag
     *
     * @var bool
     */
    protected $compress = false;
    /**
     * Strict ON flag
     *
     * Whether we're running in strict SQL mode.
     *
     * @var bool|null
     *
     * @deprecated 4.5.0 Will move to MySQLi\Connection.
     */
    protected $strict_on;
    /**
     * Settings for a failover connection.
     *
     * @var array
     */
    protected $failover = [];
    /**
     * The last query object that was executed
     * on this connection.
     *
     * @var Query
     */
    protected $last_query;
    /**
     * Connection ID
     *
     * @var false|TConnection
     */
    public $conn_id = false;
    /**
     * Result ID
     *
     * @var false|TResult
     */
    public $result_id = false;
    /**
     * Protect identifiers flag
     *
     * @var bool
     */
    public $protect_identifiers = true;
    /**
     * List of reserved identifiers
     *
     * Identifiers that must NOT be escaped.
     *
     * @var array
     */
    protected $reserved_identifiers = ['*'];
    /**
     * Identifier escape character
     *
     * @var array|string
     */
    public $escape_char = '"';
    /**
     * ESCAPE statement string
     *
     * @var string
     */
    public $like_escape_str = " ESCAPE '%s' ";
    /**
     * ESCAPE character
     *
     * @var string
     */
    public $like_escape_char = '!';
    /**
     * RegExp used to escape identifiers
     *
     * @var array
     */
    protected $preg_escape_char = [];
    /**
     * Holds previously looked up data
     * for performance reasons.
     *
     * @var array
     */
    public $data_cache = [];
    /**
     * Microtime when connection was made
     *
     * @var float
     */
    protected $connect_time = 0.0;
    /**
     * How long it took to establish connection.
     *
     * @var float
     */
    protected $connect_duration = 0.0;
    /**
     * If true, no queries will actually be
     * run against the database.
     *
     * @var bool
     */
    protected $pretend = false;
    /**
     * Transaction enabled flag
     *
     * @var bool
     */
    public $trans_enabled = true;
    /**
     * Strict transaction mode flag
     *
     * @var bool
     */
    public $trans_strict = true;
    /**
     * Transaction depth level
     *
     * @var int
     */
    protected $trans_depth = 0;
    /**
     * Transaction status flag
     *
     * Used with transactions to determine if a rollback should occur.
     *
     * @var bool
     */
    protected $trans_status = true;
    /**
     * Transaction failure flag
     *
     * Used with transactions to determine if a transaction has failed.
     *
     * @var bool
     */
    protected $trans_failure = false;
    /**
     * Whether to throw exceptions during transaction
     */
    protected bool $trans_exception = false;
    /**
     * Array of table aliases.
     *
     * @var list<string>
     */
    protected $aliased_tables = [];
    /**
     * Query Class
     *
     * @var string
     */
    protected $query_class = Query::class;
    /**
     * Default Date/Time formats
     *
     * @var array<string, string>
     */
    protected array $date_format = ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s', 'datetime-ms' => 'Y-m-d H:i:s.v', 'datetime-us' => 'Y-m-d H:i:s.u', 'time' => 'H:i:s'];
    /**
     * Saves our connection settings.
     */
    public function __construct(array $params)
    {
        if (isset($params['dateFormat'])) {
            $this->date_format = array_merge($this->date_format, $params['dateFormat']);
            unset($params['dateFormat']);
        }
        foreach ($params as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
        $query_class = str_replace('Connection', 'Query', static::class);
        if (class_exists($query_class)) {
            $this->query_class = $query_class;
        }
        if ($this->failover !== []) {
            // If there is a failover database, connect now to do failover.
            // Otherwise, Query Builder creates SQL statement with the main database config
            // (DBPrefix) even when the main database is down.
            $this->initialize();
        }
    }
    /**
     * Initializes the database connection/settings.
     *
     * @return void
     *
     * @throws DatabaseException
     */
    public function initialize()
    {
        /* If an established connection is available, then there's
         * no need to connect and select the database.
         *
         * Depending on the database driver, connID can be either
         * boolean TRUE, a resource or an object.
         */
        if ($this->conn_id) {
            return;
        }
        $this->connect_time = microtime(true);
        $connection_errors = [];
        try {
            // Connect to the database and set the connection ID
            $this->conn_id = $this->connect($this->p_connect);
        } catch (Throwable $e) {
            $this->conn_id = false;
            $connection_errors[] = sprintf('Main connection [%s]: %s', $this->db_driver, $e->get_message());
            log_message('error', 'Error connecting to the database: ' . $e);
        }
        // No connection resource? Check if there is a failover else throw an error
        if (!$this->conn_id) {
            // Check if there is a failover set
            if (!empty($this->failover) && is_array($this->failover)) {
                // Go over all the failovers
                foreach ($this->failover as $index => $failover) {
                    // Replace the current settings with those of the failover
                    foreach ($failover as $key => $val) {
                        if (property_exists($this, $key)) {
                            $this->{$key} = $val;
                        }
                    }
                    try {
                        // Try to connect
                        $this->conn_id = $this->connect($this->p_connect);
                    } catch (Throwable $e) {
                        $connection_errors[] = sprintf('Failover #%d [%s]: %s', ++$index, $this->db_driver, $e->get_message());
                        log_message('error', 'Error connecting to the database: ' . $e);
                    }
                    // If a connection is made break the foreach loop
                    if ($this->conn_id) {
                        break;
                    }
                }
            }
            // We still don't have a connection?
            if (!$this->conn_id) {
                throw new Database_Exception(sprintf('Unable to connect to the database.%s%s', PHP_EOL, implode(PHP_EOL, $connection_errors)));
            }
        }
        $this->connect_duration = microtime(true) - $this->connect_time;
    }
    /**
     * Close the database connection.
     *
     * @return void
     */
    public function close()
    {
        if ($this->conn_id) {
            $this->_close();
            $this->conn_id = false;
        }
    }
    /**
     * Keep or establish the connection if no queries have been sent for
     * a length of time exceeding the server's idle timeout.
     *
     * @return void
     */
    public function reconnect()
    {
        if ($this->ping() === false) {
            $this->close();
            $this->initialize();
        }
    }
    /**
     * Platform dependent way method for closing the connection.
     *
     * @return void
     */
    abstract protected function _close();
    /**
     * Check if the connection is still alive.
     */
    public function ping(): bool
    {
        if ($this->conn_id === false) {
            return false;
        }
        return $this->_ping();
    }
    /**
     * Driver-specific ping implementation.
     */
    protected function _ping(): bool
    {
        try {
            $result = $this->simple_query('SELECT 1');
            return $result !== false;
        } catch (Database_Exception) {
            return false;
        }
    }
    /**
     * Create a persistent database connection.
     *
     * @return false|TConnection
     */
    public function persistent_connect()
    {
        return $this->connect(true);
    }
    /**
     * Returns the actual connection object. If both a 'read' and 'write'
     * connection has been specified, you can pass either term in to
     * get that connection. If you pass either alias in and only a single
     * connection is present, it must return the sole connection.
     *
     * @return false|TConnection
     */
    public function get_connection(?string $alias = null)
    {
        // @todo work with read/write connections
        return $this->conn_id;
    }
    /**
     * Returns the name of the current database being used.
     */
    public function get_database(): string
    {
        return empty($this->database) ? '' : $this->database;
    }
    /**
     * Set DB Prefix
     *
     * Set's the DB Prefix to something new without needing to reconnect
     *
     * @param string $prefix The prefix
     */
    public function set_prefix(string $prefix = ''): string
    {
        return $this->db_prefix = $prefix;
    }
    /**
     * Returns the database prefix.
     */
    public function get_prefix(): string
    {
        return $this->db_prefix;
    }
    /**
     * The name of the platform in use (MySQLi, Postgre, SQLite3, OCI8, etc)
     */
    public function get_platform(): string
    {
        return $this->db_driver;
    }
    /**
     * Sets the Table Aliases to use. These are typically
     * collected during use of the Builder, and set here
     * so queries are built correctly.
     *
     * @return $this
     */
    public function set_aliased_tables(array $aliases)
    {
        $this->aliased_tables = $aliases;
        return $this;
    }
    /**
     * Add a table alias to our list.
     *
     * @return $this
     */
    public function add_table_alias(string $alias)
    {
        if ($alias === '') {
            return $this;
        }
        if (!in_array($alias, $this->aliased_tables, true)) {
            $this->aliased_tables[] = $alias;
        }
        return $this;
    }
    /**
     * Executes the query against the database.
     *
     * @return false|TResult
     */
    abstract protected function execute(string $sql);
    /**
     * Orchestrates a query against the database. Queries must use
     * Database\Statement objects to store the query and build it.
     * This method works with the cache.
     *
     * Should automatically handle different connections for read/write
     * queries if needed.
     *
     * @param array|string|null $binds
     *
     * @return BaseResult<TConnection, TResult>|bool|Query
     *
     * @todo BC set $queryClass default as null in 4.1
     */
    public function query(string $sql, $binds = null, bool $set_escape_flags = true, string $query_class = '')
    {
        $query_class = $query_class !== '' && $query_class !== '0' ? $query_class : $this->query_class;
        if (empty($this->conn_id)) {
            $this->initialize();
        }
        /**
         * @var Query $query
         */
        $query = new $query_class($this);
        $query->set_query($sql, $binds, $set_escape_flags);
        if (!empty($this->swap_pre) && !empty($this->db_prefix)) {
            $query->swap_prefix($this->db_prefix, $this->swap_pre);
        }
        $start_time = microtime(true);
        // Always save the last query so we can use
        // the getLastQuery() method.
        $this->last_query = $query;
        // If $pretend is true, then we just want to return
        // the actual query object here. There won't be
        // any results to return.
        if ($this->pretend) {
            $query->set_duration($start_time);
            return $query;
        }
        // Run the query for real
        try {
            $exception = null;
            $this->result_id = $this->simple_query($query->get_query());
        } catch (Database_Exception $exception) {
            $this->result_id = false;
        }
        if ($this->result_id === false) {
            $query->set_duration($start_time, $start_time);
            // This will trigger a rollback if transactions are being used
            $this->handle_trans_status();
            if ($this->db_debug && ($this->trans_depth === 0 || $this->trans_exception)) {
                // We call this function in order to roll-back queries
                // if transactions are enabled. If we don't call this here
                // the error message will trigger an exit, causing the
                // transactions to remain in limbo.
                while ($this->trans_depth !== 0) {
                    $trans_depth = $this->trans_depth;
                    $this->trans_complete();
                    if ($trans_depth === $this->trans_depth) {
                        log_message('error', 'Database: Failure during an automated transaction commit/rollback!');
                        break;
                    }
                }
                // Let others do something with this query.
                Events::trigger('DBQuery', $query);
                if ($exception instanceof Database_Exception) {
                    throw new Database_Exception($exception->get_message(), $exception->get_code(), $exception);
                }
                return false;
            }
            // Let others do something with this query.
            Events::trigger('DBQuery', $query);
            return false;
        }
        $query->set_duration($start_time);
        // Let others do something with this query
        Events::trigger('DBQuery', $query);
        // resultID is not false, so it must be successful
        if ($this->is_write_type($sql)) {
            return true;
        }
        // query is not write-type, so it must be read-type query; return QueryResult
        $result_class = str_replace('Connection', 'Result', static::class);
        return new $result_class($this->conn_id, $this->result_id);
    }
    /**
     * Performs a basic query against the database. No binding or caching
     * is performed, nor are transactions handled. Simply takes a raw
     * query string and returns the database-specific result id.
     *
     * @return false|TResult
     */
    public function simple_query(string $sql)
    {
        if (empty($this->conn_id)) {
            $this->initialize();
        }
        return $this->execute($sql);
    }
    /**
     * Disable Transactions
     *
     * This permits transactions to be disabled at run-time.
     *
     * @return void
     */
    public function trans_off()
    {
        $this->trans_enabled = false;
    }
    /**
     * Enable/disable Transaction Strict Mode
     *
     * When strict mode is enabled, if you are running multiple groups of
     * transactions, if one group fails all subsequent groups will be
     * rolled back.
     *
     * If strict mode is disabled, each group is treated autonomously,
     * meaning a failure of one group will not affect any others
     *
     * @param bool $mode = true
     *
     * @return $this
     */
    public function trans_strict(bool $mode = true)
    {
        $this->trans_strict = $mode;
        return $this;
    }
    /**
     * Start Transaction
     */
    public function trans_start(bool $test_mode = false): bool
    {
        if (!$this->trans_enabled) {
            return false;
        }
        return $this->trans_begin($test_mode);
    }
    /**
     * If set to true, exceptions are thrown during transactions.
     *
     * @return $this
     */
    public function trans_exception(bool $trans_exception)
    {
        $this->trans_exception = $trans_exception;
        return $this;
    }
    /**
     * Complete Transaction
     */
    public function trans_complete(): bool
    {
        if (!$this->trans_enabled) {
            return false;
        }
        // The query() function will set this flag to FALSE in the event that a query failed
        if ($this->trans_status === false || $this->trans_failure === true) {
            $this->trans_rollback();
            // If we are NOT running in strict mode, we will reset
            // the _trans_status flag so that subsequent groups of
            // transactions will be permitted.
            if ($this->trans_strict === false) {
                $this->trans_status = true;
            }
            return false;
        }
        return $this->trans_commit();
    }
    /**
     * Lets you retrieve the transaction flag to determine if it has failed
     */
    public function trans_status(): bool
    {
        return $this->trans_status;
    }
    /**
     * Begin Transaction
     */
    public function trans_begin(bool $test_mode = false): bool
    {
        if (!$this->trans_enabled) {
            return false;
        }
        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->trans_depth > 0) {
            $this->trans_depth++;
            return true;
        }
        if (empty($this->conn_id)) {
            $this->initialize();
        }
        // Reset the transaction failure flag.
        // If the $testMode flag is set to TRUE transactions will be rolled back
        // even if the queries produce a successful result.
        $this->trans_failure = $test_mode;
        if ($this->_trans_begin()) {
            $this->trans_depth++;
            return true;
        }
        return false;
    }
    /**
     * Commit Transaction
     */
    public function trans_commit(): bool
    {
        if (!$this->trans_enabled || $this->trans_depth === 0) {
            return false;
        }
        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->trans_depth > 1 || $this->_trans_commit()) {
            $this->trans_depth--;
            return true;
        }
        return false;
    }
    /**
     * Rollback Transaction
     */
    public function trans_rollback(): bool
    {
        if (!$this->trans_enabled || $this->trans_depth === 0) {
            return false;
        }
        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->trans_depth > 1 || $this->_trans_rollback()) {
            $this->trans_depth--;
            return true;
        }
        return false;
    }
    /**
     * Reset transaction status - to restart transactions after strict mode failure
     */
    public function reset_trans_status(): static
    {
        $this->trans_status = true;
        return $this;
    }
    /**
     * Handle transaction status when a query fails
     *
     * @internal This method is for internal database component use only
     */
    public function handle_trans_status(): void
    {
        if ($this->trans_depth !== 0) {
            $this->trans_status = false;
        }
    }
    /**
     * Begin Transaction
     */
    abstract protected function _trans_begin(): bool;
    /**
     * Commit Transaction
     */
    abstract protected function _trans_commit(): bool;
    /**
     * Rollback Transaction
     */
    abstract protected function _trans_rollback(): bool;
    /**
     * Returns a non-shared new instance of the query builder for this connection.
     *
     * @param array|string|TableName $tableName
     *
     * @return BaseBuilder
     *
     * @throws DatabaseException
     */
    public function table($table_name)
    {
        if (empty($table_name)) {
            throw new Database_Exception('You must set the database table to be used with your query.');
        }
        $class_name = str_replace('Connection', 'Builder', static::class);
        return new $class_name($table_name, $this);
    }
    /**
     * Returns a new instance of the BaseBuilder class with a cleared FROM clause.
     */
    public function new_query(): Base_Builder
    {
        // save table aliases
        $temp_aliases = $this->aliased_tables;
        $builder = $this->table(',')->from([], true);
        $this->aliased_tables = $temp_aliases;
        return $builder;
    }
    /**
     * Creates a prepared statement with the database that can then
     * be used to execute multiple statements against. Within the
     * closure, you would build the query in any normal way, though
     * the Query Builder is the expected manner.
     *
     * Example:
     *    $stmt = $db->prepare(function($db)
     *           {
     *             return $db->table('users')
     *                   ->where('id', 1)
     *                     ->get();
     *           })
     *
     * @param Closure(BaseConnection): mixed $func
     *
     * @return BasePreparedQuery|null
     */
    public function prepare(Closure $func, array $options = [])
    {
        if (empty($this->conn_id)) {
            $this->initialize();
        }
        $this->pretend();
        $sql = $func($this);
        $this->pretend(false);
        if ($sql instanceof Query_Interface) {
            $sql = $sql->get_original_query();
        }
        $class = str_ireplace('Connection', 'PreparedQuery', static::class);
        /** @var BasePreparedQuery $class */
        $class = new $class($this);
        return $class->prepare($sql, $options);
    }
    /**
     * Returns the last query's statement object.
     *
     * @return Query
     */
    public function get_last_query()
    {
        return $this->last_query;
    }
    /**
     * Returns a string representation of the last query's statement object.
     */
    public function show_last_query(): string
    {
        return (string) $this->last_query;
    }
    /**
     * Returns the time we started to connect to this database in
     * seconds with microseconds.
     *
     * Used by the Debug Toolbar's timeline.
     */
    public function get_connect_start(): ?float
    {
        return $this->connect_time;
    }
    /**
     * Returns the number of seconds with microseconds that it took
     * to connect to the database.
     *
     * Used by the Debug Toolbar's timeline.
     */
    public function get_connect_duration(int $decimals = 6): string
    {
        return number_format($this->connect_duration, $decimals);
    }
    /**
     * Protect Identifiers
     *
     * This function is used extensively by the Query Builder class, and by
     * a couple functions in this class.
     * It takes a column or table name (optionally with an alias) and inserts
     * the table prefix onto it. Some logic is necessary in order to deal with
     * column names that include the path. Consider a query like this:
     *
     * SELECT hostname.database.table.column AS c FROM hostname.database.table
     *
     * Or a query with aliasing:
     *
     * SELECT m.member_id, m.member_name FROM members AS m
     *
     * Since the column name can include up to four segments (host, DB, table, column)
     * or also have an alias prefix, we need to do a bit of work to figure this out and
     * insert the table prefix (if it exists) in the proper position, and escape only
     * the correct identifiers.
     *
     * @param array|int|string|TableName $item
     * @param bool                       $prefixSingle       Prefix a table name with no segments?
     * @param bool                       $protectIdentifiers Protect table or column names?
     * @param bool                       $fieldExists        Supplied $item contains a column name?
     *
     * @return ($item is array ? array : string)
     */
    public function protect_identifiers($item, bool $prefix_single = false, ?bool $protect_identifiers = null, bool $field_exists = true)
    {
        if (!is_bool($protect_identifiers)) {
            $protect_identifiers = $this->protect_identifiers;
        }
        if (is_array($item)) {
            $escaped_array = [];
            foreach ($item as $k => $v) {
                $escaped_array[$this->protect_identifiers($k)] = $this->protect_identifiers($v, $prefix_single, $protect_identifiers, $field_exists);
            }
            return $escaped_array;
        }
        if ($item instanceof Table_Name) {
            /** @psalm-suppress NoValue I don't know why ERROR. */
            return $this->escape_table_name($item);
        }
        // If you pass `['column1', 'column2']`, `$item` will be int because the array keys are int.
        $item = (string) $item;
        // This is basically a bug fix for queries that use MAX, MIN, etc.
        // If a parenthesis is found we know that we do not need to
        // escape the data or add a prefix. There's probably a more graceful
        // way to deal with this, but I'm not thinking of it
        //
        // Added exception for single quotes as well, we don't want to alter
        // literal strings.
        if (strcspn($item, "()'") !== strlen($item)) {
            /** @psalm-suppress NoValue I don't know why ERROR. */
            return $item;
        }
        // Do not protect identifiers and do not prefix, no swap prefix, there is nothing to do
        if ($protect_identifiers === false && $prefix_single === false && $this->swap_pre === '') {
            /** @psalm-suppress NoValue I don't know why ERROR. */
            return $item;
        }
        // Convert tabs or multiple spaces into single spaces
        /** @psalm-suppress NoValue I don't know why ERROR. */
        $item = preg_replace('/\s+/', ' ', trim($item));
        // If the item has an alias declaration we remove it and set it aside.
        // Note: strripos() is used in order to support spaces in table names
        if ($offset = strripos($item, ' AS ')) {
            $alias = $protect_identifiers ? substr($item, $offset, 4) . $this->escape_identifiers(substr($item, $offset + 4)) : substr($item, $offset);
            $item = substr($item, 0, $offset);
        } elseif ($offset = strrpos($item, ' ')) {
            $alias = $protect_identifiers ? ' ' . $this->escape_identifiers(substr($item, $offset + 1)) : substr($item, $offset);
            $item = substr($item, 0, $offset);
        } else {
            $alias = '';
        }
        // Break the string apart if it contains periods, then insert the table prefix
        // in the correct location, assuming the period doesn't indicate that we're dealing
        // with an alias. While we're at it, we will escape the components
        if (str_contains($item, '.')) {
            return $this->protect_dot_item($item, $alias, $protect_identifiers, $field_exists);
        }
        // In some cases, especially 'from', we end up running through
        // protect_identifiers twice. This algorithm won't work when
        // it contains the escapeChar so strip it out.
        $item = trim($item, $this->escape_char);
        // Is there a table prefix? If not, no need to insert it
        if ($this->db_prefix !== '') {
            // Verify table prefix and replace if necessary
            if ($this->swap_pre !== '' && str_starts_with($item, $this->swap_pre)) {
                $item = preg_replace('/^' . $this->swap_pre . '(\S+?)/', $this->db_prefix . '\1', $item);
            } elseif ($prefix_single && !str_starts_with($item, $this->db_prefix)) {
                $item = $this->db_prefix . $item;
            }
        }
        if ($protect_identifiers === true && !in_array($item, $this->reserved_identifiers, true)) {
            $item = $this->escape_identifiers($item);
        }
        return $item . $alias;
    }
    private function protect_dot_item(string $item, string $alias, bool $protect_identifiers, bool $field_exists): string
    {
        $parts = explode('.', $item);
        // Does the first segment of the exploded item match
        // one of the aliases previously identified? If so,
        // we have nothing more to do other than escape the item
        //
        // NOTE: The ! empty() condition prevents this method
        // from breaking when QB isn't enabled.
        if (!empty($this->aliased_tables) && in_array($parts[0], $this->aliased_tables, true)) {
            if ($protect_identifiers) {
                foreach ($parts as $key => $val) {
                    if (!in_array($val, $this->reserved_identifiers, true)) {
                        $parts[$key] = $this->escape_identifiers($val);
                    }
                }
                $item = implode('.', $parts);
            }
            return $item . $alias;
        }
        // Is there a table prefix defined in the config file? If not, no need to do anything
        if ($this->db_prefix !== '') {
            // We now add the table prefix based on some logic.
            // Do we have 4 segments (hostname.database.table.column)?
            // If so, we add the table prefix to the column name in the 3rd segment.
            if (isset($parts[3])) {
                $i = 2;
            } elseif (isset($parts[2])) {
                $i = 1;
            } else {
                $i = 0;
            }
            // This flag is set when the supplied $item does not contain a field name.
            // This can happen when this function is being called from a JOIN.
            if ($field_exists === false) {
                $i++;
            }
            // Verify table prefix and replace if necessary
            if ($this->swap_pre !== '' && str_starts_with($parts[$i], $this->swap_pre)) {
                $parts[$i] = preg_replace('/^' . $this->swap_pre . '(\S+?)/', $this->db_prefix . '\1', $parts[$i]);
            } elseif (!str_starts_with($parts[$i], $this->db_prefix)) {
                $parts[$i] = $this->db_prefix . $parts[$i];
            }
            // Put the parts back together
            $item = implode('.', $parts);
        }
        if ($protect_identifiers) {
            $item = $this->escape_identifiers($item);
        }
        return $item . $alias;
    }
    /**
     * Escape the SQL Identifier
     *
     * This function escapes single identifier.
     *
     * @param non-empty-string|TableName $item
     */
    public function escape_identifier($item): string
    {
        if ($item === '') {
            return '';
        }
        if ($item instanceof Table_Name) {
            return $this->escape_table_name($item);
        }
        return $this->escape_char . str_replace($this->escape_char, $this->escape_char . $this->escape_char, $item) . $this->escape_char;
    }
    /**
     * Returns escaped table name with alias.
     */
    private function escape_table_name(Table_Name $table_name): string
    {
        $alias = $table_name->get_alias();
        return $this->escape_identifier($table_name->get_actual_table_name()) . ($alias !== '' ? ' ' . $this->escape_identifier($alias) : '');
    }
    /**
     * Escape the SQL Identifiers
     *
     * This function escapes column and table names
     *
     * @param array|string $item
     *
     * @return ($item is array ? array : string)
     */
    public function escape_identifiers($item)
    {
        if ($this->escape_char === '' || empty($item) || in_array($item, $this->reserved_identifiers, true)) {
            return $item;
        }
        if (is_array($item)) {
            foreach ($item as $key => $value) {
                $item[$key] = $this->escape_identifiers($value);
            }
            return $item;
        }
        // Avoid breaking functions and literal values inside queries
        if (ctype_digit($item) || $item[0] === "'" || $this->escape_char !== '"' && $item[0] === '"' || str_contains($item, '(')) {
            return $item;
        }
        if ($this->preg_escape_char === []) {
            if (is_array($this->escape_char)) {
                $this->preg_escape_char = [preg_quote($this->escape_char[0], '/'), preg_quote($this->escape_char[1], '/'), $this->escape_char[0], $this->escape_char[1]];
            } else {
                $this->preg_escape_char[0] = $this->preg_escape_char[1] = preg_quote($this->escape_char, '/');
                $this->preg_escape_char[2] = $this->preg_escape_char[3] = $this->escape_char;
            }
        }
        foreach ($this->reserved_identifiers as $id) {
            /** @psalm-suppress NoValue I don't know why ERROR. */
            if (str_contains($item, '.' . $id)) {
                return preg_replace('/' . $this->preg_escape_char[0] . '?([^' . $this->preg_escape_char[1] . '\.]+)' . $this->preg_escape_char[1] . '?\./i', $this->preg_escape_char[2] . '$1' . $this->preg_escape_char[3] . '.', $item);
            }
        }
        /** @psalm-suppress NoValue I don't know why ERROR. */
        return preg_replace('/' . $this->preg_escape_char[0] . '?([^' . $this->preg_escape_char[1] . '\.]+)' . $this->preg_escape_char[1] . '?(\.)?/i', $this->preg_escape_char[2] . '$1' . $this->preg_escape_char[3] . '$2', $item);
    }
    /**
     * Prepends a database prefix if one exists in configuration
     *
     * @throws DatabaseException
     */
    public function prefix_table(string $table = ''): string
    {
        if ($table === '') {
            throw new Database_Exception('A table name is required for that operation.');
        }
        return $this->db_prefix . $table;
    }
    /**
     * Returns the total number of rows affected by this query.
     */
    abstract public function affected_rows(): int;
    /**
     * "Smart" Escape String
     *
     * Escapes data based on type.
     * Sets boolean and null types
     *
     * @param array|bool|float|int|object|string|null $str
     *
     * @return ($str is array ? array : float|int|string)
     */
    public function escape($str)
    {
        if (is_array($str)) {
            return array_map($this->escape(...), $str);
        }
        if ($str instanceof Stringable) {
            if ($str instanceof Raw_Sql) {
                return $str->__toString();
            }
            $str = (string) $str;
        }
        if (is_string($str)) {
            return "'" . $this->escape_string($str) . "'";
        }
        if (is_bool($str)) {
            return $str === false ? 0 : 1;
        }
        return $str ?? 'NULL';
    }
    /**
     * Escape String
     *
     * @param list<string|Stringable>|string|Stringable $str  Input string
     * @param bool                                      $like Whether the string will be used in a LIKE condition
     *
     * @return list<string>|string
     */
    public function escape_string($str, bool $like = false)
    {
        if (is_array($str)) {
            foreach ($str as $key => $val) {
                $str[$key] = $this->escape_string($val, $like);
            }
            return $str;
        }
        if ($str instanceof Stringable) {
            if ($str instanceof Raw_Sql) {
                return $str->__toString();
            }
            $str = (string) $str;
        }
        $str = $this->_escape_string($str);
        // escape LIKE condition wildcards
        if ($like) {
            return str_replace([$this->like_escape_char, '%', '_'], [$this->like_escape_char . $this->like_escape_char, $this->like_escape_char . '%', $this->like_escape_char . '_'], $str);
        }
        return $str;
    }
    /**
     * Escape LIKE String
     *
     * Calls the individual driver for platform
     * specific escaping for LIKE conditions
     *
     * @param list<string|Stringable>|string|Stringable $str
     *
     * @return list<string>|string
     */
    public function escape_like_string($str)
    {
        return $this->escape_string($str, true);
    }
    /**
     * Platform independent string escape.
     *
     * Will likely be overridden in child classes.
     */
    protected function _escape_string(string $str): string
    {
        return str_replace("'", "''", remove_invisible_characters($str, false));
    }
    /**
     * This function enables you to call PHP database functions that are not natively included
     * in CodeIgniter, in a platform independent manner.
     *
     * @param array ...$params
     *
     * @throws DatabaseException
     */
    public function call_function(string $function_name, ...$params): bool
    {
        $driver = $this->get_driver_function_prefix();
        if (!str_starts_with($function_name, $driver)) {
            $function_name = $driver . $function_name;
        }
        if (!function_exists($function_name)) {
            if ($this->db_debug) {
                throw new Database_Exception('This feature is not available for the database you are using.');
            }
            return false;
        }
        return $function_name(...$params);
    }
    /**
     * Get the prefix of the function to access the DB.
     */
    protected function get_driver_function_prefix(): string
    {
        return strtolower($this->db_driver) . '_';
    }
    // --------------------------------------------------------------------
    // META Methods
    // --------------------------------------------------------------------
    /**
     * Returns an array of table names
     *
     * @return false|list<string>
     *
     * @throws DatabaseException
     */
    public function list_tables(bool $constrain_by_prefix = false)
    {
        if (isset($this->data_cache['table_names']) && $this->data_cache['table_names']) {
            return $constrain_by_prefix ? preg_grep("/^{$this->db_prefix}/", $this->data_cache['table_names']) : $this->data_cache['table_names'];
        }
        $sql = $this->_list_tables($constrain_by_prefix);
        if ($sql === false) {
            if ($this->db_debug) {
                throw new Database_Exception('This feature is not available for the database you are using.');
            }
            return false;
        }
        $this->data_cache['table_names'] = [];
        $query = $this->query($sql);
        foreach ($query->get_result_array() as $row) {
            /** @var string $table */
            $table = $row['table_name'] ?? $row['TABLE_NAME'] ?? $row[array_key_first($row)];
            $this->data_cache['table_names'][] = $table;
        }
        return $this->data_cache['table_names'];
    }
    /**
     * Determine if a particular table exists
     *
     * @param bool $cached Whether to use data cache
     */
    public function table_exists(string $table_name, bool $cached = true): bool
    {
        if ($cached) {
            return in_array($this->protect_identifiers($table_name, true, false, false), $this->list_tables(), true);
        }
        if (false === $sql = $this->_list_tables(false, $table_name)) {
            if ($this->db_debug) {
                throw new Database_Exception('This feature is not available for the database you are using.');
            }
            return false;
        }
        $table_exists = $this->query($sql)->get_result_array() !== [];
        // if cache has been built already
        if (!empty($this->data_cache['table_names'])) {
            $key = array_search(strtolower($table_name), array_map(strtolower(...), $this->data_cache['table_names']), true);
            // table doesn't exist but still in cache - lets reset cache, it can be rebuilt later
            // OR if table does exist but is not found in cache
            if ($key !== false && !$table_exists || $key === false && $table_exists) {
                $this->reset_data_cache();
            }
        }
        return $table_exists;
    }
    /**
     * Fetch Field Names
     *
     * @param string|TableName $tableName
     *
     * @return false|list<string>
     *
     * @throws DatabaseException
     */
    public function get_field_names($table_name)
    {
        $table = $table_name instanceof Table_Name ? $table_name->get_table_name() : $table_name;
        // Is there a cached result?
        if (isset($this->data_cache['field_names'][$table])) {
            return $this->data_cache['field_names'][$table];
        }
        if (empty($this->conn_id)) {
            $this->initialize();
        }
        if (false === $sql = $this->_list_columns($table_name)) {
            if ($this->db_debug) {
                throw new Database_Exception('This feature is not available for the database you are using.');
            }
            return false;
        }
        $query = $this->query($sql);
        $this->data_cache['field_names'][$table] = [];
        foreach ($query->get_result_array() as $row) {
            // Do we know from where to get the column's name?
            if (!isset($key)) {
                if (isset($row['column_name'])) {
                    $key = 'column_name';
                } elseif (isset($row['COLUMN_NAME'])) {
                    $key = 'COLUMN_NAME';
                } else {
                    // We have no other choice but to just get the first element's key.
                    $key = key($row);
                }
            }
            $this->data_cache['field_names'][$table][] = $row[$key];
        }
        return $this->data_cache['field_names'][$table];
    }
    /**
     * Determine if a particular field exists
     */
    public function field_exists(string $field_name, string $table_name): bool
    {
        return in_array($field_name, $this->get_field_names($table_name), true);
    }
    /**
     * Returns an object with field data
     *
     * @return list<stdClass>
     */
    public function get_field_data(string $table)
    {
        return $this->_field_data($this->protect_identifiers($table, true, false, false));
    }
    /**
     * Returns an object with key data
     *
     * @return array<string, stdClass>
     */
    public function get_index_data(string $table)
    {
        return $this->_index_data($this->protect_identifiers($table, true, false, false));
    }
    /**
     * Returns an object with foreign key data
     *
     * @return array<string, stdClass>
     */
    public function get_foreign_key_data(string $table)
    {
        return $this->_foreign_key_data($this->protect_identifiers($table, true, false, false));
    }
    /**
     * Converts array of arrays generated by _foreignKeyData() to array of objects
     *
     * @return array<string, stdClass>
     *
     * array[
     *    {constraint_name} =>
     *        stdClass[
     *            'constraint_name'     => string,
     *            'table_name'          => string,
     *            'column_name'         => string[],
     *            'foreign_table_name'  => string,
     *            'foreign_column_name' => string[],
     *            'on_delete'           => string,
     *            'on_update'           => string,
     *            'match'               => string
     *        ]
     * ]
     */
    protected function foreign_key_data_to_objects(array $data)
    {
        $ret_val = [];
        foreach ($data as $row) {
            $name = $row['constraint_name'];
            // for sqlite generate name
            if ($name === null) {
                $name = $row['table_name'] . '_' . implode('_', $row['column_name']) . '_foreign';
            }
            $obj = new stdClass();
            $obj->constraint_name = $name;
            $obj->table_name = $row['table_name'];
            $obj->column_name = $row['column_name'];
            $obj->foreign_table_name = $row['foreign_table_name'];
            $obj->foreign_column_name = $row['foreign_column_name'];
            $obj->on_delete = $row['on_delete'];
            $obj->on_update = $row['on_update'];
            $obj->match = $row['match'];
            $ret_val[$name] = $obj;
        }
        return $ret_val;
    }
    /**
     * Disables foreign key checks temporarily.
     *
     * @return bool
     */
    public function disable_foreign_key_checks()
    {
        $sql = $this->_disable_foreign_key_checks();
        if ($sql === '') {
            // The feature is not supported.
            return false;
        }
        return $this->query($sql);
    }
    /**
     * Enables foreign key checks temporarily.
     *
     * @return bool
     */
    public function enable_foreign_key_checks()
    {
        $sql = $this->_enable_foreign_key_checks();
        if ($sql === '') {
            // The feature is not supported.
            return false;
        }
        return $this->query($sql);
    }
    /**
     * Allows the engine to be set into a mode where queries are not
     * actually executed, but they are still generated, timed, etc.
     *
     * This is primarily used by the prepared query functionality.
     *
     * @return $this
     */
    public function pretend(bool $pretend = true)
    {
        $this->pretend = $pretend;
        return $this;
    }
    /**
     * Empties our data cache. Especially helpful during testing.
     *
     * @return $this
     */
    public function reset_data_cache()
    {
        $this->data_cache = [];
        return $this;
    }
    /**
     * Determines if the statement is a write-type query or not.
     *
     * @param string $sql
     */
    public function is_write_type($sql): bool
    {
        return (bool) preg_match('/^\s*(WITH\s.+(\s|[)]))?"?(SET|INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|TRUNCATE|LOAD|COPY|ALTER|RENAME|GRANT|REVOKE|LOCK|UNLOCK|REINDEX|MERGE)\s(?!.*\sRETURNING\s)/is', $sql);
    }
    /**
     * Returns the last error code and message.
     *
     * Must return an array with keys 'code' and 'message':
     *
     * @return array{code: int|string|null, message: string|null}
     */
    abstract public function error(): array;
    /**
     * Insert ID
     *
     * @return int|string
     */
    abstract public function insert_id();
    /**
     * Generates the SQL for listing tables in a platform-dependent manner.
     *
     * @param string|null $tableName If $tableName is provided will return only this table if exists.
     *
     * @return false|string
     */
    abstract protected function _list_tables(bool $constrain_by_prefix = false, ?string $table_name = null);
    /**
     * Generates a platform-specific query string so that the column names can be fetched.
     *
     * @param string|TableName $table
     *
     * @return false|string
     */
    abstract protected function _list_columns($table = '');
    /**
     * Platform-specific field data information.
     *
     * @see getFieldData()
     *
     * @return list<stdClass>
     */
    abstract protected function _field_data(string $table): array;
    /**
     * Platform-specific index data.
     *
     * @see    getIndexData()
     *
     * @return array<string, stdClass>
     */
    abstract protected function _index_data(string $table): array;
    /**
     * Platform-specific foreign keys data.
     *
     * @see    getForeignKeyData()
     *
     * @return array<string, stdClass>
     */
    abstract protected function _foreign_key_data(string $table): array;
    /**
     * Platform-specific SQL statement to disable foreign key checks.
     *
     * If this feature is not supported, return empty string.
     *
     * @TODO This method should be moved to an interface that represents foreign key support.
     *
     * @return string
     *
     * @see disableForeignKeyChecks()
     */
    protected function _disable_foreign_key_checks()
    {
        return '';
    }
    /**
     * Platform-specific SQL statement to enable foreign key checks.
     *
     * If this feature is not supported, return empty string.
     *
     * @TODO This method should be moved to an interface that represents foreign key support.
     *
     * @return string
     *
     * @see enableForeignKeyChecks()
     */
    protected function _enable_foreign_key_checks()
    {
        return '';
    }
    /**
     * Accessor for properties if they exist.
     *
     * @return array|bool|float|int|object|resource|string|null
     */
    public function __get(string $key)
    {
        if (property_exists($this, $key)) {
            return $this->{$key};
        }
        return null;
    }
    /**
     * Checker for properties existence.
     */
    public function __isset(string $key): bool
    {
        return property_exists($this, $key);
    }
}