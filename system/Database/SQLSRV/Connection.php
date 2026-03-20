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
namespace Code_Igniter\Database\SQLSRV;

use Code_Igniter\Database\Base_Connection;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Database\Table_Name;
use stdClass;
/**
 * Connection for SQLSRV
 *
 * @extends BaseConnection<resource, resource>
 */
class Connection extends Base_Connection
{
    /**
     * Database driver
     *
     * @var string
     */
    public $db_driver = 'SQLSRV';
    /**
     * Database name
     *
     * @var string
     */
    public $database;
    /**
     * Scrollable flag
     *
     * Determines what cursor type to use when executing queries.
     *
     * FALSE or SQLSRV_CURSOR_FORWARD would increase performance,
     * but would disable num_rows() (and possibly insert_id())
     *
     * @var false|string
     */
    public $scrollable;
    /**
     * Identifier escape character
     *
     * @var string
     */
    public $escape_char = '"';
    /**
     * Database schema
     *
     * @var string
     */
    public $schema = 'dbo';
    /**
     * Quoted identifier flag
     *
     * Whether to use SQL-92 standard quoted identifier
     * (double quotes) or brackets for identifier escaping.
     *
     * @var bool
     */
    protected $_quoted_identifier = true;
    /**
     * List of reserved identifiers
     *
     * Identifiers that must NOT be escaped.
     *
     * @var list<string>
     */
    protected $_reserved_identifiers = ['*'];
    /**
     * Class constructor
     */
    public function __construct(array $params)
    {
        parent::__construct($params);
        // This is only supported as of SQLSRV 3.0
        if ($this->scrollable === null) {
            $this->scrollable = defined('SQLSRV_CURSOR_CLIENT_BUFFERED') ? SQLSRV_CURSOR_CLIENT_BUFFERED : false;
        }
    }
    /**
     * Connect to the database.
     *
     * @return false|resource
     *
     * @throws DatabaseException
     */
    public function connect(bool $persistent = false)
    {
        $charset = in_array(strtolower($this->charset), ['utf-8', 'utf8'], true) ? 'UTF-8' : SQLSRV_ENC_CHAR;
        $connection = ['UID' => empty($this->username) ? '' : $this->username, 'PWD' => empty($this->password) ? '' : $this->password, 'Database' => $this->database, 'ConnectionPooling' => $persistent ? 1 : 0, 'CharacterSet' => $charset, 'Encrypt' => $this->encrypt === true ? 1 : 0, 'ReturnDatesAsStrings' => 1];
        // If the username and password are both empty, assume this is a
        // 'Windows Authentication Mode' connection.
        if (empty($connection['UID']) && empty($connection['PWD'])) {
            unset($connection['UID'], $connection['PWD']);
        }
        if (!str_contains($this->hostname, ',') && $this->port !== '') {
            $this->hostname .= ', ' . $this->port;
        }
        sqlsrv_configure('WarningsReturnAsErrors', 0);
        $this->conn_id = sqlsrv_connect($this->hostname, $connection);
        if ($this->conn_id !== false) {
            // Determine how identifiers are escaped
            $query = $this->query('SELECT CASE WHEN (@@OPTIONS | 256) = @@OPTIONS THEN 1 ELSE 0 END AS qi');
            $query = $query->get_result_object();
            $this->_quoted_identifier = empty($query) ? false : (bool) $query[0]->qi;
            $this->escape_char = $this->_quoted_identifier ? '"' : ['[', ']'];
            return $this->conn_id;
        }
        throw new Database_Exception($this->get_all_error_messages());
    }
    /**
     * For exception message
     *
     * @internal
     */
    public function get_all_error_messages(): string
    {
        $errors = [];
        foreach (sqlsrv_errors() as $error) {
            $errors[] = sprintf('%s SQLSTATE: %s, code: %s', $error['message'], $error['SQLSTATE'], $error['code']);
        }
        return implode("\n", $errors);
    }
    /**
     * Close the database connection.
     *
     * @return void
     */
    protected function _close()
    {
        sqlsrv_close($this->conn_id);
    }
    /**
     * Platform-dependant string escape
     */
    protected function _escape_string(string $str): string
    {
        return str_replace("'", "''", remove_invisible_characters($str, false));
    }
    /**
     * Insert ID
     */
    public function insert_id(): int
    {
        return (int) ($this->query('SELECT SCOPE_IDENTITY() AS insert_id')->get_row()->insert_id ?? 0);
    }
    /**
     * Generates the SQL for listing tables in a platform-dependent manner.
     *
     * @param string|null $tableName If $tableName is provided will return only this table if exists.
     */
    protected function _list_tables(bool $prefix_limit = false, ?string $table_name = null): string
    {
        $sql = 'SELECT [TABLE_NAME] AS "name"' . ' FROM [INFORMATION_SCHEMA].[TABLES] ' . ' WHERE ' . " [TABLE_SCHEMA] = '" . $this->schema . "'    ";
        if ($table_name !== null) {
            return $sql .= ' AND [TABLE_NAME] LIKE ' . $this->escape($table_name);
        }
        if ($prefix_limit && $this->db_prefix !== '') {
            $sql .= " AND [TABLE_NAME] LIKE '" . $this->escape_like_string($this->db_prefix) . "%' " . sprintf($this->like_escape_str, $this->like_escape_char);
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
            $table_name = $this->escape(strtolower($table->get_actual_table_name()));
        } else {
            $table_name = $this->escape($this->db_prefix . strtolower($table));
        }
        return 'SELECT [COLUMN_NAME] ' . ' FROM [INFORMATION_SCHEMA].[COLUMNS]' . ' WHERE  [TABLE_NAME] = ' . $table_name . ' AND [TABLE_SCHEMA] = ' . $this->escape($this->schema);
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
        $sql = 'EXEC sp_helpindex ' . $this->escape($this->schema . '.' . $table);
        if (($query = $this->query($sql)) === false) {
            throw new Database_Exception(lang('Database.failGetIndexData'));
        }
        $query = $query->get_result_object();
        $ret_val = [];
        foreach ($query as $row) {
            $obj = new stdClass();
            $obj->name = $row->index_name;
            $_fields = explode(',', trim($row->index_keys));
            $obj->fields = array_map(trim(...), $_fields);
            if (str_contains($row->index_description, 'primary key located on')) {
                $obj->type = 'PRIMARY';
            } else {
                $obj->type = str_contains($row->index_description, 'nonclustered, unique') ? 'UNIQUE' : 'INDEX';
            }
            $ret_val[$obj->name] = $obj;
        }
        return $ret_val;
    }
    /**
     * Returns an array of objects with Foreign key data
     * referenced_object_id  parent_object_id
     *
     * @return array<string, stdClass>
     *
     * @throws DatabaseException
     */
    protected function _foreign_key_data(string $table): array
    {
        $sql = 'SELECT
                f.name as constraint_name,
                OBJECT_NAME (f.parent_object_id) as table_name,
                COL_NAME(fc.parent_object_id,fc.parent_column_id) column_name,
                OBJECT_NAME(f.referenced_object_id) foreign_table_name,
                COL_NAME(fc.referenced_object_id,fc.referenced_column_id) foreign_column_name,
                rc.delete_rule,
                rc.update_rule,
                rc.match_option
                FROM
                sys.foreign_keys AS f
                INNER JOIN sys.foreign_key_columns AS fc ON f.OBJECT_ID = fc.constraint_object_id
                INNER JOIN sys.tables t ON t.OBJECT_ID = fc.referenced_object_id
                INNER JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_NAME = f.name
                WHERE OBJECT_NAME (f.parent_object_id) = ' . $this->escape($table);
        if (($query = $this->query($sql)) === false) {
            throw new Database_Exception(lang('Database.failGetForeignKeyData'));
        }
        $query = $query->get_result_object();
        $indexes = [];
        foreach ($query as $row) {
            $indexes[$row->constraint_name]['constraint_name'] = $row->constraint_name;
            $indexes[$row->constraint_name]['table_name'] = $row->table_name;
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
     * Disables foreign key checks temporarily.
     *
     * @return string
     */
    protected function _disable_foreign_key_checks()
    {
        return 'EXEC sp_MSforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT ALL"';
    }
    /**
     * Enables foreign key checks temporarily.
     *
     * @return string
     */
    protected function _enable_foreign_key_checks()
    {
        return 'EXEC sp_MSforeachtable "ALTER TABLE ? WITH CHECK CHECK CONSTRAINT ALL"';
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
        $sql = 'SELECT
                COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION,
                COLUMN_DEFAULT, IS_NULLABLE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME= ' . $this->escape($table);
        if (($query = $this->query($sql)) === false) {
            throw new Database_Exception(lang('Database.failGetFieldData'));
        }
        $query = $query->get_result_object();
        $ret_val = [];
        for ($i = 0, $c = count($query); $i < $c; $i++) {
            $ret_val[$i] = new stdClass();
            $ret_val[$i]->name = $query[$i]->COLUMN_NAME;
            $ret_val[$i]->type = $query[$i]->DATA_TYPE;
            $ret_val[$i]->max_length = $query[$i]->CHARACTER_MAXIMUM_LENGTH > 0 ? $query[$i]->CHARACTER_MAXIMUM_LENGTH : ($query[$i]->CHARACTER_MAXIMUM_LENGTH === -1 ? 'max' : $query[$i]->NUMERIC_PRECISION);
            $ret_val[$i]->nullable = $query[$i]->IS_NULLABLE !== 'NO';
            $ret_val[$i]->default = $this->normalize_default($query[$i]->COLUMN_DEFAULT);
        }
        return $ret_val;
    }
    /**
     * Normalizes SQL Server COLUMN_DEFAULT values.
     * Removes wrapping parentheses and handles basic conversions.
     */
    private function normalize_default(?string $default): ?string
    {
        if ($default === null) {
            return null;
        }
        $default = trim($default);
        // Remove outer parentheses (handles both single and double wrapping)
        while (preg_match('/^\((.*)\)$/', $default, $matches)) {
            $default = trim($matches[1]);
        }
        // Handle NULL literal
        if (strcasecmp($default, 'NULL') === 0) {
            return null;
        }
        // Handle string literals - remove quotes and unescape
        if (preg_match("/^'(.*)'\$/s", $default, $matches)) {
            return str_replace("''", "'", $matches[1]);
        }
        return $default;
    }
    /**
     * Begin Transaction
     */
    protected function _trans_begin(): bool
    {
        return sqlsrv_begin_transaction($this->conn_id);
    }
    /**
     * Commit Transaction
     */
    protected function _trans_commit(): bool
    {
        return sqlsrv_commit($this->conn_id);
    }
    /**
     * Rollback Transaction
     */
    protected function _trans_rollback(): bool
    {
        return sqlsrv_rollback($this->conn_id);
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
        $error = ['code' => '00000', 'message' => ''];
        $sqlsrv_errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
        if (!is_array($sqlsrv_errors)) {
            return $error;
        }
        $sqlsrv_error = array_shift($sqlsrv_errors);
        if (isset($sqlsrv_error['SQLSTATE'])) {
            $error['code'] = isset($sqlsrv_error['code']) ? $sqlsrv_error['SQLSTATE'] . '/' . $sqlsrv_error['code'] : $sqlsrv_error['SQLSTATE'];
        } elseif (isset($sqlsrv_error['code'])) {
            $error['code'] = $sqlsrv_error['code'];
        }
        if (isset($sqlsrv_error['message'])) {
            $error['message'] = $sqlsrv_error['message'];
        }
        return $error;
    }
    /**
     * Returns the total number of rows affected by this query.
     */
    public function affected_rows(): int
    {
        if ($this->result_id === false) {
            return 0;
        }
        return sqlsrv_rows_affected($this->result_id);
    }
    /**
     * Select a specific database table to use.
     *
     * @return bool
     */
    public function set_database(?string $database_name = null)
    {
        if ($database_name === null || $database_name === '') {
            $database_name = $this->database;
        }
        if (empty($this->conn_id)) {
            $this->initialize();
        }
        if ($this->execute('USE ' . $this->_escape_string($database_name))) {
            $this->database = $database_name;
            $this->data_cache = [];
            return true;
        }
        return false;
    }
    /**
     * Executes the query against the database.
     *
     * @return false|resource
     */
    protected function execute(string $sql)
    {
        $stmt = $this->scrollable === false || $this->is_write_type($sql) ? sqlsrv_query($this->conn_id, $sql) : sqlsrv_query($this->conn_id, $sql, [], ['Scrollable' => $this->scrollable]);
        if ($stmt === false) {
            $trace = debug_backtrace();
            $first = array_shift($trace);
            log_message('error', "{message}\nin {exFile} on line {exLine}.\n{trace}", ['message' => $this->get_all_error_messages(), 'exFile' => clean_path($first['file']), 'exLine' => $first['line'], 'trace' => render_backtrace($trace)]);
            if ($this->db_debug) {
                throw new Database_Exception($this->get_all_error_messages());
            }
        }
        return $stmt;
    }
    /**
     * Returns the last error encountered by this connection.
     *
     * @return array<string, int|string>
     *
     * @deprecated Use `error()` instead.
     */
    public function get_error()
    {
        $error = ['code' => '00000', 'message' => ''];
        $sqlsrv_errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
        if (!is_array($sqlsrv_errors)) {
            return $error;
        }
        $sqlsrv_error = array_shift($sqlsrv_errors);
        if (isset($sqlsrv_error['SQLSTATE'])) {
            $error['code'] = isset($sqlsrv_error['code']) ? $sqlsrv_error['SQLSTATE'] . '/' . $sqlsrv_error['code'] : $sqlsrv_error['SQLSTATE'];
        } elseif (isset($sqlsrv_error['code'])) {
            $error['code'] = $sqlsrv_error['code'];
        }
        if (isset($sqlsrv_error['message'])) {
            $error['message'] = $sqlsrv_error['message'];
        }
        return $error;
    }
    /**
     * The name of the platform in use (MySQLi, mssql, etc)
     */
    public function get_platform(): string
    {
        return $this->db_driver;
    }
    /**
     * Returns a string containing the version of the database being used.
     */
    public function get_version(): string
    {
        $info = [];
        if (isset($this->data_cache['version'])) {
            return $this->data_cache['version'];
        }
        if (!$this->conn_id) {
            $this->initialize();
        }
        if (($info = sqlsrv_server_info($this->conn_id)) === []) {
            return '';
        }
        return isset($info['SQLServerVersion']) ? $this->data_cache['version'] = $info['SQLServerVersion'] : '';
    }
    /**
     * Determines if a query is a "write" type.
     *
     * Overrides BaseConnection::isWriteType, adding additional read query types.
     *
     * @param string $sql
     */
    public function is_write_type($sql): bool
    {
        if (preg_match('/^\s*"?(EXEC\s*sp_rename)\s/i', $sql)) {
            return true;
        }
        return parent::is_write_type($sql);
    }
}