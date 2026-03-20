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
namespace Code_Igniter\Database\Postgre;

use Code_Igniter\Database\Base_Connection;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Database\Raw_Sql;
use Code_Igniter\Database\Table_Name;
use ErrorException;
use Pg_Sql\Connection as PgSqlConnection;
use Pg_Sql\Result as PgSqlResult;
use stdClass;
use Stringable;
/**
 * Connection for Postgre
 *
 * @extends BaseConnection<PgSqlConnection, PgSqlResult>
 */
class Connection extends Base_Connection
{
    /**
     * Database driver
     *
     * @var string
     */
    public $db_driver = 'Postgre';
    /**
     * Database schema
     *
     * @var string
     */
    public $schema = 'public';
    /**
     * Identifier escape character
     *
     * @var string
     */
    public $escape_char = '"';
    protected $connect_timeout;
    protected $options;
    protected $sslmode;
    protected $service;
    /**
     * Connect to the database.
     *
     * @return false|PgSqlConnection
     */
    public function connect(bool $persistent = false)
    {
        if (empty($this->DSN)) {
            $this->build_dsn();
        }
        // Convert DSN string
        // @TODO This format is for PDO_PGSQL.
        //      https://www.php.net/manual/en/ref.pdo-pgsql.connection.php
        //      Should deprecate?
        if (mb_strpos($this->DSN, 'pgsql:') === 0) {
            $this->convert_dsn();
        }
        $this->conn_id = $persistent ? pg_pconnect($this->DSN) : pg_connect($this->DSN);
        if ($this->conn_id !== false) {
            if ($persistent && pg_connection_status($this->conn_id) === PGSQL_CONNECTION_BAD && pg_ping($this->conn_id) === false) {
                $error = pg_last_error($this->conn_id);
                throw new Database_Exception($error);
            }
            if (!empty($this->schema)) {
                $this->simple_query("SET search_path TO {$this->schema},public");
            }
            if ($this->set_client_encoding($this->charset) === false) {
                $error = pg_last_error($this->conn_id);
                throw new Database_Exception($error);
            }
        }
        return $this->conn_id;
    }
    /**
     * Converts the DSN with semicolon syntax.
     *
     * @return void
     */
    private function convert_dsn()
    {
        // Strip pgsql
        $this->DSN = mb_substr($this->DSN, 6);
        // Convert semicolons to spaces in DSN format like:
        // pgsql:host=localhost;port=5432;dbname=database_name
        // https://www.php.net/manual/en/function.pg-connect.php
        $allowed_params = ['host', 'port', 'dbname', 'user', 'password', 'connect_timeout', 'options', 'sslmode', 'service'];
        $parameters = explode(';', $this->DSN);
        $output = '';
        $previous_parameter = '';
        foreach ($parameters as $parameter) {
            [$key, $value] = explode('=', $parameter, 2);
            if (in_array($key, $allowed_params, true)) {
                if ($previous_parameter !== '') {
                    if (array_search($key, $allowed_params, true) < array_search($previous_parameter, $allowed_params, true)) {
                        $output .= ';';
                    } else {
                        $output .= ' ';
                    }
                }
                $output .= $parameter;
                $previous_parameter = $key;
            } else {
                $output .= ';' . $parameter;
            }
        }
        $this->DSN = $output;
    }
    /**
     * Close the database connection.
     *
     * @return void
     */
    protected function _close()
    {
        pg_close($this->conn_id);
    }
    /**
     * Ping the database connection.
     */
    protected function _ping(): bool
    {
        return pg_ping($this->conn_id);
    }
    /**
     * Select a specific database table to use.
     */
    public function set_database(string $database_name): bool
    {
        return false;
    }
    /**
     * Returns a string containing the version of the database being used.
     */
    public function get_version(): string
    {
        if (isset($this->data_cache['version'])) {
            return $this->data_cache['version'];
        }
        if (!$this->conn_id) {
            $this->initialize();
        }
        $pg_version = pg_version($this->conn_id);
        $this->data_cache['version'] = isset($pg_version['server']) ? preg_match('/^(\d+\.\d+)/', $pg_version['server'], $matches) ? $matches[1] : '' : '';
        return $this->data_cache['version'];
    }
    /**
     * Executes the query against the database.
     *
     * @return false|PgSqlResult
     */
    protected function execute(string $sql)
    {
        try {
            return pg_query($this->conn_id, $sql);
        } catch (ErrorException $e) {
            $trace = array_slice($e->get_trace(), 2);
            // remove the call to error handler
            log_message('error', "{message}\nin {exFile} on line {exLine}.\n{trace}", ['message' => $e->get_message(), 'exFile' => clean_path($e->get_file()), 'exLine' => $e->get_line(), 'trace' => render_backtrace($trace)]);
            if ($this->db_debug) {
                throw new Database_Exception($e->get_message(), $e->get_code(), $e);
            }
        }
        return false;
    }
    /**
     * Get the prefix of the function to access the DB.
     */
    protected function get_driver_function_prefix(): string
    {
        return 'pg_';
    }
    /**
     * Returns the total number of rows affected by this query.
     */
    public function affected_rows(): int
    {
        if ($this->result_id === false) {
            return 0;
        }
        return pg_affected_rows($this->result_id);
    }
    /**
     * "Smart" Escape String
     *
     * Escapes data based on type
     *
     * @param array|bool|float|int|object|string|null $str
     *
     * @return ($str is array ? array : float|int|string)
     */
    public function escape($str)
    {
        if (!$this->conn_id) {
            $this->initialize();
        }
        if ($str instanceof Stringable) {
            if ($str instanceof Raw_Sql) {
                return $str->__toString();
            }
            $str = (string) $str;
        }
        if (is_string($str)) {
            return pg_escape_literal($this->conn_id, $str);
        }
        if (is_bool($str)) {
            return $str ? 'TRUE' : 'FALSE';
        }
        return parent::escape($str);
    }
    /**
     * Platform-dependant string escape
     */
    protected function _escape_string(string $str): string
    {
        if (!$this->conn_id) {
            $this->initialize();
        }
        return pg_escape_string($this->conn_id, $str);
    }
    /**
     * Generates the SQL for listing tables in a platform-dependent manner.
     *
     * @param string|null $tableName If $tableName is provided will return only this table if exists.
     */
    protected function _list_tables(bool $prefix_limit = false, ?string $table_name = null): string
    {
        $sql = 'SELECT "table_name" FROM "information_schema"."tables" WHERE "table_schema" = \'' . $this->schema . "'";
        if ($table_name !== null) {
            return $sql . ' AND "table_name" LIKE ' . $this->escape($table_name);
        }
        if ($prefix_limit && $this->db_prefix !== '') {
            return $sql . ' AND "table_name" LIKE \'' . $this->escape_like_string($this->db_prefix) . "%' " . sprintf($this->like_escape_str, $this->like_escape_char);
        }
        return $sql;
    }
    /**
     * Generates a platform-specific query string so that the column names can be fetched.
     *
     * @param string|TableName $table
     */
    protected function _list_columns($table = ''): string
    {
        if ($table instanceof Table_Name) {
            $table_name = $this->escape($table->get_actual_table_name());
        } else {
            $table_name = $this->escape($this->db_prefix . strtolower($table));
        }
        return 'SELECT "column_name"
			FROM "information_schema"."columns"
			WHERE LOWER("table_name") = ' . $table_name . ' ORDER BY "ordinal_position"';
    }
    /**
     * Returns an array of objects with field data
     *
     * @return list<stdClass>
     *
     * @throws DatabaseException
     */
    protected function _field_data(string $table): array
    {
        $sql = 'SELECT "column_name", "data_type", "character_maximum_length", "numeric_precision", "column_default",  "is_nullable"
            FROM "information_schema"."columns"
            WHERE LOWER("table_name") = ' . $this->escape(strtolower($table)) . ' ORDER BY "ordinal_position"';
        if (($query = $this->query($sql)) === false) {
            throw new Database_Exception(lang('Database.failGetFieldData'));
        }
        $query = $query->get_result_object();
        $ret_val = [];
        for ($i = 0, $c = count($query); $i < $c; $i++) {
            $ret_val[$i] = new stdClass();
            $ret_val[$i]->name = $query[$i]->column_name;
            $ret_val[$i]->type = $query[$i]->data_type;
            $ret_val[$i]->max_length = $query[$i]->character_maximum_length > 0 ? $query[$i]->character_maximum_length : $query[$i]->numeric_precision;
            $ret_val[$i]->nullable = $query[$i]->is_nullable === 'YES';
            $ret_val[$i]->default = $query[$i]->column_default;
        }
        return $ret_val;
    }
    /**
     * Returns an array of objects with index data
     *
     * @return array<string, stdClass>
     *
     * @throws DatabaseException
     */
    protected function _index_data(string $table): array
    {
        $sql = 'SELECT "indexname", "indexdef"
			FROM "pg_indexes"
			WHERE LOWER("tablename") = ' . $this->escape(strtolower($table)) . '
			AND "schemaname" = ' . $this->escape('public');
        if (($query = $this->query($sql)) === false) {
            throw new Database_Exception(lang('Database.failGetIndexData'));
        }
        $query = $query->get_result_object();
        $ret_val = [];
        foreach ($query as $row) {
            $obj = new stdClass();
            $obj->name = $row->indexname;
            $_fields = explode(',', preg_replace('/^.*\((.+?)\)$/', '$1', trim($row->indexdef)));
            $obj->fields = array_map(trim(...), $_fields);
            if (str_starts_with($row->indexdef, 'CREATE UNIQUE INDEX pk')) {
                $obj->type = 'PRIMARY';
            } else {
                $obj->type = str_starts_with($row->indexdef, 'CREATE UNIQUE') ? 'UNIQUE' : 'INDEX';
            }
            $ret_val[$obj->name] = $obj;
        }
        return $ret_val;
    }
    /**
     * Returns an array of objects with Foreign key data
     *
     * @return array<string, stdClass>
     *
     * @throws DatabaseException
     */
    protected function _foreign_key_data(string $table): array
    {
        $sql = 'SELECT c.constraint_name,
                x.table_name,
                x.column_name,
                y.table_name as foreign_table_name,
                y.column_name as foreign_column_name,
                c.delete_rule,
                c.update_rule,
                c.match_option
                FROM information_schema.referential_constraints c
                JOIN information_schema.key_column_usage x
                    on x.constraint_name = c.constraint_name
                JOIN information_schema.key_column_usage y
                    on y.ordinal_position = x.position_in_unique_constraint
                    and y.constraint_name = c.unique_constraint_name
                WHERE x.table_name = ' . $this->escape($table) . 'order by c.constraint_name, x.ordinal_position';
        if (($query = $this->query($sql)) === false) {
            throw new Database_Exception(lang('Database.failGetForeignKeyData'));
        }
        $query = $query->get_result_object();
        $indexes = [];
        foreach ($query as $row) {
            $indexes[$row->constraint_name]['constraint_name'] = $row->constraint_name;
            $indexes[$row->constraint_name]['table_name'] = $table;
            $indexes[$row->constraint_name]['column_name'][] = $row->column_name;
            $indexes[$row->constraint_name]['foreign_table_name'] = $row->foreign_table_name;
            $indexes[$row->constraint_name]['foreign_column_name'][] = $row->foreign_column_name;
            $indexes[$row->constraint_name]['on_delete'] = $row->delete_rule;
            $indexes[$row->constraint_name]['on_update'] = $row->update_rule;
            $indexes[$row->constraint_name]['match'] = $row->match_option;
        }
        return $this->foreign_key_data_to_objects($indexes);
    }
    /**
     * Returns platform-specific SQL to disable foreign key checks.
     *
     * @return string
     */
    protected function _disable_foreign_key_checks()
    {
        return 'SET CONSTRAINTS ALL DEFERRED';
    }
    /**
     * Returns platform-specific SQL to enable foreign key checks.
     *
     * @return string
     */
    protected function _enable_foreign_key_checks()
    {
        return 'SET CONSTRAINTS ALL IMMEDIATE;';
    }
    /**
     * Returns the last error code and message.
     * Must return this format: ['code' => string|int, 'message' => string]
     * intval(code) === 0 means "no error".
     *
     * @return array<string, int|string>
     */
    public function error(): array
    {
        return ['code' => '', 'message' => pg_last_error($this->conn_id)];
    }
    /**
     * @return int|string
     */
    public function insert_id()
    {
        $v = pg_version($this->conn_id);
        // 'server' key is only available since PostgreSQL 7.4
        $v = explode(' ', $v['server'])[0] ?? 0;
        $table = func_num_args() > 0 ? func_get_arg(0) : null;
        $column = func_num_args() > 1 ? func_get_arg(1) : null;
        if ($table === null && $v >= '8.1') {
            $sql = 'SELECT LASTVAL() AS ins_id';
        } elseif ($table !== null) {
            if ($column !== null && $v >= '8.0') {
                $sql = "SELECT pg_get_serial_sequence('{$table}', '{$column}') AS seq";
                $query = $this->query($sql);
                $query = $query->get_row();
                $seq = $query->seq;
            } else {
                // seq_name passed in table parameter
                $seq = $table;
            }
            $sql = "SELECT CURRVAL('{$seq}') AS ins_id";
        } else {
            return pg_last_oid($this->result_id);
        }
        $query = $this->query($sql);
        $query = $query->get_row();
        return (int) $query->ins_id;
    }
    /**
     * Build a DSN from the provided parameters
     *
     * @return void
     */
    protected function build_dsn()
    {
        if ($this->DSN !== '') {
            $this->DSN = '';
        }
        // If UNIX sockets are used, we shouldn't set a port
        if (str_contains($this->hostname, '/')) {
            $this->port = '';
        }
        if ($this->hostname !== '') {
            $this->DSN = "host={$this->hostname} ";
        }
        // ctype_digit only accepts strings
        $port = (string) $this->port;
        if ($port !== '' && ctype_digit($port)) {
            $this->DSN .= "port={$port} ";
        }
        if ($this->username !== '') {
            $this->DSN .= "user={$this->username} ";
            // An empty password is valid!
            // password must be set to null to ignore it.
            if ($this->password !== null) {
                $this->DSN .= "password='{$this->password}' ";
            }
        }
        if ($this->database !== '') {
            $this->DSN .= "dbname={$this->database} ";
        }
        // We don't have these options as elements in our standard configuration
        // array, but they might be set by parse_url() if the configuration was
        // provided via string> Example:
        //
        // Postgre://username:password@localhost:5432/database?connect_timeout=5&sslmode=1
        foreach (['connect_timeout', 'options', 'sslmode', 'service'] as $key) {
            if (isset($this->{$key}) && is_string($this->{$key}) && $this->{$key} !== '') {
                $this->DSN .= "{$key}='{$this->{$key}}' ";
            }
        }
        $this->DSN = rtrim($this->DSN);
    }
    /**
     * Set client encoding
     */
    protected function set_client_encoding(string $charset): bool
    {
        return pg_set_client_encoding($this->conn_id, $charset) === 0;
    }
    /**
     * Begin Transaction
     */
    protected function _trans_begin(): bool
    {
        return (bool) pg_query($this->conn_id, 'BEGIN');
    }
    /**
     * Commit Transaction
     */
    protected function _trans_commit(): bool
    {
        return (bool) pg_query($this->conn_id, 'COMMIT');
    }
    /**
     * Rollback Transaction
     */
    protected function _trans_rollback(): bool
    {
        return (bool) pg_query($this->conn_id, 'ROLLBACK');
    }
}