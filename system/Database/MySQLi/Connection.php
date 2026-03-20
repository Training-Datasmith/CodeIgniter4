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
namespace Code_Igniter\Database\My_Sq_Li;

use Code_Igniter\Database\Base_Connection;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Database\Table_Name;
use Code_Igniter\Exceptions\LogicException;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use stdClass;
use Throwable;
/**
 * Connection for MySQLi
 *
 * @extends BaseConnection<mysqli, mysqli_result>
 */
class Connection extends Base_Connection
{
    /**
     * Database driver
     *
     * @var string
     */
    public $db_driver = 'MySQLi';
    /**
     * DELETE hack flag
     *
     * Whether to use the MySQL "delete hack" which allows the number
     * of affected rows to be shown. Uses a preg_replace when enabled,
     * adding a bit more processing to all queries.
     *
     * @var bool
     */
    public $delete_hack = true;
    /**
     * Identifier escape character
     *
     * @var string
     */
    public $escape_char = '`';
    /**
     * MySQLi object
     *
     * Has to be preserved without being assigned to $connId.
     *
     * @var false|mysqli
     */
    public $mysqli;
    /**
     * MySQLi constant
     *
     * For unbuffered queries use `MYSQLI_USE_RESULT`.
     *
     * Default mode for buffered queries uses `MYSQLI_STORE_RESULT`.
     *
     * @var int
     */
    public $result_mode = MYSQLI_STORE_RESULT;
    /**
     * Use MYSQLI_OPT_INT_AND_FLOAT_NATIVE
     *
     * @var bool
     */
    public $number_native = false;
    /**
     * Use MYSQLI_CLIENT_FOUND_ROWS
     *
     * Whether affectedRows() should return number of rows found,
     * or number of rows changed, after an UPDATE query.
     *
     * @var bool
     */
    public $found_rows = false;
    /**
     * Connect to the database.
     *
     * @return false|mysqli
     *
     * @throws DatabaseException
     */
    public function connect(bool $persistent = false)
    {
        // Do we have a socket path?
        if ($this->hostname[0] === '/') {
            $hostname = null;
            $port = null;
            $socket = $this->hostname;
        } else {
            $hostname = $persistent ? 'p:' . $this->hostname : $this->hostname;
            $port = empty($this->port) ? null : $this->port;
            $socket = '';
        }
        $client_flags = $this->compress === true ? MYSQLI_CLIENT_COMPRESS : 0;
        $this->mysqli = mysqli_init();
        mysqli_report(MYSQLI_REPORT_ALL & ~MYSQLI_REPORT_INDEX);
        $this->mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        if ($this->number_native === true) {
            $this->mysqli->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        }
        if ($this->strict_on !== null) {
            if ($this->strict_on) {
                $this->mysqli->options(MYSQLI_INIT_COMMAND, "SET SESSION sql_mode = CONCAT(@@sql_mode, ',', 'STRICT_ALL_TABLES')");
            } else {
                $this->mysqli->options(MYSQLI_INIT_COMMAND, "SET SESSION sql_mode = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(\n                                        @@sql_mode,\n                                        'STRICT_ALL_TABLES,', ''),\n                                    ',STRICT_ALL_TABLES', ''),\n                                'STRICT_ALL_TABLES', ''),\n                            'STRICT_TRANS_TABLES,', ''),\n                        ',STRICT_TRANS_TABLES', ''),\n                    'STRICT_TRANS_TABLES', '')");
            }
        }
        if (is_array($this->encrypt)) {
            $ssl = [];
            if (!empty($this->encrypt['ssl_key'])) {
                $ssl['key'] = $this->encrypt['ssl_key'];
            }
            if (!empty($this->encrypt['ssl_cert'])) {
                $ssl['cert'] = $this->encrypt['ssl_cert'];
            }
            if (!empty($this->encrypt['ssl_ca'])) {
                $ssl['ca'] = $this->encrypt['ssl_ca'];
            }
            if (!empty($this->encrypt['ssl_capath'])) {
                $ssl['capath'] = $this->encrypt['ssl_capath'];
            }
            if (!empty($this->encrypt['ssl_cipher'])) {
                $ssl['cipher'] = $this->encrypt['ssl_cipher'];
            }
            if ($ssl !== []) {
                if (isset($this->encrypt['ssl_verify'])) {
                    if ($this->encrypt['ssl_verify']) {
                        if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
                            $this->mysqli->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, 1);
                        }
                    } elseif (defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT') && version_compare($this->mysqli->client_info, 'mysqlnd 5.6', '>=')) {
                        $client_flags += MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
                    }
                }
                $this->mysqli->ssl_set($ssl['key'] ?? null, $ssl['cert'] ?? null, $ssl['ca'] ?? null, $ssl['capath'] ?? null, $ssl['cipher'] ?? null);
            }
            $client_flags += MYSQLI_CLIENT_SSL;
        }
        if ($this->found_rows) {
            $client_flags += MYSQLI_CLIENT_FOUND_ROWS;
        }
        try {
            if ($this->mysqli->real_connect($hostname, $this->username, $this->password, $this->database, $port, $socket, $client_flags)) {
                if (!$this->mysqli->set_charset($this->charset)) {
                    log_message('error', "Database: Unable to set the configured connection charset ('{$this->charset}').");
                    $this->mysqli->close();
                    if ($this->db_debug) {
                        throw new Database_Exception('Unable to set client connection character set: ' . $this->charset);
                    }
                    return false;
                }
                return $this->mysqli;
            }
        } catch (Throwable $e) {
            // Clean sensitive information from errors.
            $msg = $e->get_message();
            $msg = str_replace($this->username, '****', $msg);
            $msg = str_replace($this->password, '****', $msg);
            throw new Database_Exception($msg, $e->get_code(), $e);
        }
        return false;
    }
    /**
     * Close the database connection.
     *
     * @return void
     */
    protected function _close()
    {
        $this->conn_id->close();
    }
    /**
     * Select a specific database table to use.
     */
    public function set_database(string $database_name): bool
    {
        if ($database_name === '') {
            $database_name = $this->database;
        }
        if (empty($this->conn_id)) {
            $this->initialize();
        }
        if ($this->conn_id->select_db($database_name)) {
            $this->database = $database_name;
            return true;
        }
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
        if (empty($this->mysqli)) {
            $this->initialize();
        }
        return $this->data_cache['version'] = $this->mysqli->server_info;
    }
    /**
     * Executes the query against the database.
     *
     * @return false|mysqli_result
     */
    protected function execute(string $sql)
    {
        while ($this->conn_id->more_results()) {
            $this->conn_id->next_result();
            if ($res = $this->conn_id->store_result()) {
                $res->free();
            }
        }
        try {
            return $this->conn_id->query($this->prep_query($sql), $this->result_mode);
        } catch (mysqli_sql_exception $e) {
            log_message('error', "{message}\nin {exFile} on line {exLine}.\n{trace}", ['message' => $e->get_message(), 'exFile' => clean_path($e->get_file()), 'exLine' => $e->get_line(), 'trace' => render_backtrace($e->get_trace())]);
            if ($this->db_debug) {
                throw new Database_Exception($e->get_message(), $e->get_code(), $e);
            }
        }
        return false;
    }
    /**
     * Prep the query. If needed, each database adapter can prep the query string
     */
    protected function prep_query(string $sql): string
    {
        // mysqli_affected_rows() returns 0 for "DELETE FROM TABLE" queries. This hack
        // modifies the query so that it a proper number of affected rows is returned.
        if ($this->delete_hack === true && preg_match('/^\s*DELETE\s+FROM\s+(\S+)\s*$/i', $sql)) {
            return trim($sql) . ' WHERE 1=1';
        }
        return $sql;
    }
    /**
     * Returns the total number of rows affected by this query.
     */
    public function affected_rows(): int
    {
        return $this->conn_id->affected_rows ?? 0;
    }
    /**
     * Platform-dependant string escape
     */
    protected function _escape_string(string $str): string
    {
        if (!$this->conn_id) {
            $this->initialize();
        }
        return $this->conn_id->real_escape_string($str);
    }
    /**
     * Escape Like String Direct
     * There are a few instances where MySQLi queries cannot take the
     * additional "ESCAPE x" parameter for specifying the escape character
     * in "LIKE" strings, and this handles those directly with a backslash.
     *
     * @param list<string>|string $str Input string
     *
     * @return list<string>|string
     */
    public function escape_like_string_direct($str)
    {
        if (is_array($str)) {
            foreach ($str as $key => $val) {
                $str[$key] = $this->escape_like_string_direct($val);
            }
            return $str;
        }
        $str = $this->_escape_string($str);
        // Escape LIKE condition wildcards
        return str_replace([$this->like_escape_char, '%', '_'], ['\\' . $this->like_escape_char, '\%', '\_'], $str);
    }
    /**
     * Generates the SQL for listing tables in a platform-dependent manner.
     * Uses escapeLikeStringDirect().
     *
     * @param string|null $tableName If $tableName is provided will return only this table if exists.
     */
    protected function _list_tables(bool $prefix_limit = false, ?string $table_name = null): string
    {
        $sql = 'SHOW TABLES FROM ' . $this->escape_identifier($this->database);
        if ((string) $table_name !== '') {
            return $sql . ' LIKE ' . $this->escape($table_name);
        }
        if ($prefix_limit && $this->db_prefix !== '') {
            return $sql . " LIKE '" . $this->escape_like_string_direct($this->db_prefix) . "%'";
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
        $table_name = $this->protect_identifiers($table, true, null, false);
        return 'SHOW COLUMNS FROM ' . $table_name;
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
        $table = $this->protect_identifiers($table, true, null, false);
        if (($query = $this->query('SHOW COLUMNS FROM ' . $table)) === false) {
            throw new Database_Exception(lang('Database.failGetFieldData'));
        }
        $query = $query->get_result_object();
        $ret_val = [];
        for ($i = 0, $c = count($query); $i < $c; $i++) {
            $ret_val[$i] = new stdClass();
            $ret_val[$i]->name = $query[$i]->Field;
            sscanf($query[$i]->Type, '%[a-z](%d)', $ret_val[$i]->type, $ret_val[$i]->max_length);
            $ret_val[$i]->nullable = $query[$i]->Null === 'YES';
            $ret_val[$i]->default = $query[$i]->Default;
            $ret_val[$i]->primary_key = (int) ($query[$i]->Key === 'PRI');
        }
        return $ret_val;
    }
    /**
     * Returns an array of objects with index data
     *
     * @return array<string, stdClass>
     *
     * @throws DatabaseException
     * @throws LogicException
     */
    protected function _index_data(string $table): array
    {
        $table = $this->protect_identifiers($table, true, null, false);
        if (($query = $this->query('SHOW INDEX FROM ' . $table)) === false) {
            throw new Database_Exception(lang('Database.failGetIndexData'));
        }
        $indexes = $query->get_result_array();
        if ($indexes === []) {
            return [];
        }
        $keys = [];
        foreach ($indexes as $index) {
            if (empty($keys[$index['Key_name']])) {
                $keys[$index['Key_name']] = new stdClass();
                $keys[$index['Key_name']]->name = $index['Key_name'];
                if ($index['Key_name'] === 'PRIMARY') {
                    $type = 'PRIMARY';
                } elseif ($index['Index_type'] === 'FULLTEXT') {
                    $type = 'FULLTEXT';
                } elseif ($index['Non_unique']) {
                    $type = $index['Index_type'] === 'SPATIAL' ? 'SPATIAL' : 'INDEX';
                } else {
                    $type = 'UNIQUE';
                }
                $keys[$index['Key_name']]->type = $type;
            }
            $keys[$index['Key_name']]->fields[] = $index['Column_name'];
        }
        return $keys;
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
        $sql = '
                SELECT
                    tc.CONSTRAINT_NAME,
                    tc.TABLE_NAME,
                    kcu.COLUMN_NAME,
                    rc.REFERENCED_TABLE_NAME,
                    kcu.REFERENCED_COLUMN_NAME,
                    rc.DELETE_RULE,
                    rc.UPDATE_RULE,
                    rc.MATCH_OPTION
                FROM information_schema.table_constraints AS tc
                INNER JOIN information_schema.referential_constraints AS rc
                    ON tc.constraint_name = rc.constraint_name
                    AND tc.constraint_schema = rc.constraint_schema
                INNER JOIN information_schema.key_column_usage AS kcu
                    ON tc.constraint_name = kcu.constraint_name
                    AND tc.constraint_schema = kcu.constraint_schema
                WHERE
                    tc.constraint_type = ' . $this->escape('FOREIGN KEY') . ' AND
                    tc.table_schema = ' . $this->escape($this->database) . ' AND
                    tc.table_name = ' . $this->escape($table);
        if (($query = $this->query($sql)) === false) {
            throw new Database_Exception(lang('Database.failGetForeignKeyData'));
        }
        $query = $query->get_result_object();
        $indexes = [];
        foreach ($query as $row) {
            $indexes[$row->CONSTRAINT_NAME]['constraint_name'] = $row->CONSTRAINT_NAME;
            $indexes[$row->CONSTRAINT_NAME]['table_name'] = $row->TABLE_NAME;
            $indexes[$row->CONSTRAINT_NAME]['column_name'][] = $row->COLUMN_NAME;
            $indexes[$row->CONSTRAINT_NAME]['foreign_table_name'] = $row->REFERENCED_TABLE_NAME;
            $indexes[$row->CONSTRAINT_NAME]['foreign_column_name'][] = $row->REFERENCED_COLUMN_NAME;
            $indexes[$row->CONSTRAINT_NAME]['on_delete'] = $row->DELETE_RULE;
            $indexes[$row->CONSTRAINT_NAME]['on_update'] = $row->UPDATE_RULE;
            $indexes[$row->CONSTRAINT_NAME]['match'] = $row->MATCH_OPTION;
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
        return 'SET FOREIGN_KEY_CHECKS=0';
    }
    /**
     * Returns platform-specific SQL to enable foreign key checks.
     *
     * @return string
     */
    protected function _enable_foreign_key_checks()
    {
        return 'SET FOREIGN_KEY_CHECKS=1';
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
        if (!empty($this->mysqli->connect_errno)) {
            return ['code' => $this->mysqli->connect_errno, 'message' => $this->mysqli->connect_error];
        }
        return ['code' => $this->conn_id->errno, 'message' => $this->conn_id->error];
    }
    /**
     * Insert ID
     */
    public function insert_id(): int
    {
        return $this->conn_id->insert_id;
    }
    /**
     * Begin Transaction
     */
    protected function _trans_begin(): bool
    {
        return $this->conn_id->begin_transaction();
    }
    /**
     * Commit Transaction
     */
    protected function _trans_commit(): bool
    {
        return $this->conn_id->commit();
    }
    /**
     * Rollback Transaction
     */
    protected function _trans_rollback(): bool
    {
        return $this->conn_id->rollback();
    }
}