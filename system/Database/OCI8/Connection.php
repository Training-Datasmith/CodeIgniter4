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
namespace Code_Igniter\Database\OCI8;

use Code_Igniter\Database\Base_Connection;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Database\Query;
use Code_Igniter\Database\Table_Name;
use ErrorException;
use stdClass;
/**
 * Connection for OCI8
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
    protected $db_driver = 'OCI8';
    /**
     * Identifier escape character
     *
     * @var string
     */
    public $escape_char = '"';
    /**
     * List of reserved identifiers
     *
     * Identifiers that must NOT be escaped.
     *
     * @var array
     */
    protected $reserved_identifiers = ['*', 'rownum'];
    protected $valid_ds_ns = [
        // TNS
        'tns' => '/^\(DESCRIPTION=(\(.+\)){2,}\)$/',
        // Easy Connect string (Oracle 10g+).
        // https://docs.oracle.com/en/database/oracle/oracle-database/23/netag/configuring-naming-methods.html#GUID-36F3A17D-843C-490A-8A23-FB0FE005F8E8
        // [//]host[:port][/[service_name][:server_type][/instance_name]]
        'ec' => '/^
            (\/\/)?
            (\[)?[a-z0-9.:_-]+(\])? # Host or IP address
            (:[1-9][0-9]{0,4})?     # Port
            (
                (\/)
                ([a-z0-9.$_]+)?     # Service name
                (:[a-z]+)?          # Server type
                (\/[a-z0-9$_]+)?    # Instance name
            )?
        $/ix',
        // Instance name (defined in tnsnames.ora)
        'in' => '/^[a-z0-9$_]+$/i',
    ];
    /**
     * Reset $stmtId flag
     *
     * Used by storedProcedure() to prevent execute() from
     * re-setting the statement ID.
     */
    protected $reset_stmt_id = true;
    /**
     * Statement ID
     *
     * @var resource
     */
    protected $stmt_id;
    /**
     * Commit mode flag
     *
     * @used-by PreparedQuery::_execute()
     *
     * @var int
     */
    public $commit_mode = OCI_COMMIT_ON_SUCCESS;
    /**
     * Cursor ID
     *
     * @var resource
     */
    protected $cursor_id;
    /**
     * Latest inserted table name.
     *
     * @used-by PreparedQuery::_execute()
     *
     * @var string|null
     */
    public $last_inserted_table_name;
    /**
     * confirm DSN format.
     */
    private function is_valid_dsn(): bool
    {
        if ($this->DSN === null || $this->DSN === '') {
            return false;
        }
        foreach ($this->valid_ds_ns as $regexp) {
            if (preg_match($regexp, $this->DSN)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Connect to the database.
     *
     * @return false|resource
     */
    public function connect(bool $persistent = false)
    {
        if (!$this->is_valid_dsn()) {
            $this->build_dsn();
        }
        $func = $persistent ? 'oci_pconnect' : 'oci_connect';
        return $this->charset === '' ? $func($this->username, $this->password, $this->DSN) : $func($this->username, $this->password, $this->DSN, $this->charset);
    }
    /**
     * Close the database connection.
     *
     * @return void
     */
    protected function _close()
    {
        if (is_resource($this->cursor_id)) {
            oci_free_statement($this->cursor_id);
        }
        if (is_resource($this->stmt_id)) {
            oci_free_statement($this->stmt_id);
        }
        oci_close($this->conn_id);
    }
    /**
     * Ping the database connection.
     */
    protected function _ping(): bool
    {
        try {
            $result = $this->simple_query('SELECT 1 FROM DUAL');
            return $result !== false;
        } catch (Database_Exception) {
            return false;
        }
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
        if ($this->conn_id === false) {
            $this->initialize();
        }
        if (($version_string = oci_server_version($this->conn_id)) === false) {
            return '';
        }
        if (preg_match('#Release\s(\d+(?:\.\d+)+)#', $version_string, $match)) {
            return $this->data_cache['version'] = $match[1];
        }
        return '';
    }
    /**
     * Executes the query against the database.
     *
     * @return false|resource
     */
    protected function execute(string $sql)
    {
        try {
            if ($this->reset_stmt_id === true) {
                $this->stmt_id = oci_parse($this->conn_id, $sql);
            }
            oci_set_prefetch($this->stmt_id, 1000);
            $result = oci_execute($this->stmt_id, $this->commit_mode) ? $this->stmt_id : false;
            $insert_table_name = $this->parse_insert_table_name($sql);
            if ($result && $insert_table_name !== '') {
                $this->last_inserted_table_name = $insert_table_name;
            }
            return $result;
        } catch (ErrorException $e) {
            $trace = array_slice($e->get_trace(), 2);
            // remove call to error handler
            log_message('error', "{message}\nin {exFile} on line {exLine}.\n{trace}", ['message' => $e->get_message(), 'exFile' => clean_path($e->get_file()), 'exLine' => $e->get_line(), 'trace' => render_backtrace($trace)]);
            if ($this->db_debug) {
                throw new Database_Exception($e->get_message(), $e->get_code(), $e);
            }
        }
        return false;
    }
    /**
     * Get the table name for the insert statement from sql.
     */
    public function parse_insert_table_name(string $sql): string
    {
        $comment_stripped_sql = preg_replace(['/\/\*(.|\n)*?\*\//m', '/--.+/'], '', $sql);
        $is_insert_query = str_starts_with(strtoupper(ltrim($comment_stripped_sql)), 'INSERT');
        if (!$is_insert_query) {
            return '';
        }
        preg_match('/(?is)\b(?:into)\s+("?\w+"?)/', $comment_stripped_sql, $match);
        $table_name = $match[1] ?? '';
        return str_starts_with($table_name, '"') ? trim($table_name, '"') : strtoupper($table_name);
    }
    /**
     * Returns the total number of rows affected by this query.
     */
    public function affected_rows(): int
    {
        return oci_num_rows($this->stmt_id);
    }
    /**
     * Generates the SQL for listing tables in a platform-dependent manner.
     *
     * @param string|null $tableName If $tableName is provided will return only this table if exists.
     */
    protected function _list_tables(bool $prefix_limit = false, ?string $table_name = null): string
    {
        $sql = 'SELECT "TABLE_NAME" FROM "USER_TABLES"';
        if ($table_name !== null) {
            return $sql . ' WHERE "TABLE_NAME" LIKE ' . $this->escape($table_name);
        }
        if ($prefix_limit && $this->db_prefix !== '') {
            return $sql . ' WHERE "TABLE_NAME" LIKE \'' . $this->escape_like_string($this->db_prefix) . "%' " . sprintf($this->like_escape_str, $this->like_escape_char);
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
            $table_name = $this->escape(strtoupper($table->get_actual_table_name()));
            $owner = $this->username;
        } elseif (str_contains($table, '.')) {
            sscanf($table, '%[^.].%s', $owner, $table_name);
            $table_name = $this->escape(strtoupper($this->db_prefix . $table_name));
        } else {
            $owner = $this->username;
            $table_name = $this->escape(strtoupper($this->db_prefix . $table));
        }
        return 'SELECT COLUMN_NAME FROM ALL_TAB_COLUMNS
			WHERE UPPER(OWNER) = ' . $this->escape(strtoupper($owner)) . '
				AND UPPER(TABLE_NAME) = ' . $table_name;
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
        if (str_contains($table, '.')) {
            sscanf($table, '%[^.].%s', $owner, $table);
        } else {
            $owner = $this->username;
        }
        $sql = 'SELECT COLUMN_NAME, DATA_TYPE, CHAR_LENGTH, DATA_PRECISION, DATA_LENGTH, DATA_DEFAULT, NULLABLE
			FROM ALL_TAB_COLUMNS
			WHERE UPPER(OWNER) = ' . $this->escape(strtoupper($owner)) . '
				AND UPPER(TABLE_NAME) = ' . $this->escape(strtoupper($table));
        if (($query = $this->query($sql)) === false) {
            throw new Database_Exception(lang('Database.failGetFieldData'));
        }
        $query = $query->get_result_object();
        $retval = [];
        for ($i = 0, $c = count($query); $i < $c; $i++) {
            $retval[$i] = new stdClass();
            $retval[$i]->name = $query[$i]->COLUMN_NAME;
            $retval[$i]->type = $query[$i]->DATA_TYPE;
            $length = $query[$i]->CHAR_LENGTH > 0 ? $query[$i]->CHAR_LENGTH : $query[$i]->DATA_PRECISION;
            $length ??= $query[$i]->DATA_LENGTH;
            $retval[$i]->max_length = $length;
            $retval[$i]->nullable = $query[$i]->NULLABLE === 'Y';
            $retval[$i]->default = $this->normalize_default($query[$i]->DATA_DEFAULT);
        }
        return $retval;
    }
    /**
     * Removes trailing whitespace from default values
     * returned in database column metadata queries.
     */
    private function normalize_default(?string $default): ?string
    {
        if ($default === null) {
            return $default;
        }
        return rtrim($default);
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
        if (str_contains($table, '.')) {
            sscanf($table, '%[^.].%s', $owner, $table);
        } else {
            $owner = $this->username;
        }
        $sql = 'SELECT AIC.INDEX_NAME, UC.CONSTRAINT_TYPE, AIC.COLUMN_NAME ' . ' FROM ALL_IND_COLUMNS AIC ' . ' LEFT JOIN USER_CONSTRAINTS UC ON AIC.INDEX_NAME = UC.CONSTRAINT_NAME AND AIC.TABLE_NAME = UC.TABLE_NAME ' . 'WHERE AIC.TABLE_NAME = ' . $this->escape(strtolower($table)) . ' ' . 'AND AIC.TABLE_OWNER = ' . $this->escape(strtoupper($owner)) . ' ' . ' ORDER BY UC.CONSTRAINT_TYPE, AIC.COLUMN_POSITION';
        if (($query = $this->query($sql)) === false) {
            throw new Database_Exception(lang('Database.failGetIndexData'));
        }
        $query = $query->get_result_object();
        $ret_val = [];
        $constraint_types = ['P' => 'PRIMARY', 'U' => 'UNIQUE'];
        foreach ($query as $row) {
            if (isset($ret_val[$row->INDEX_NAME])) {
                $ret_val[$row->INDEX_NAME]->fields[] = $row->COLUMN_NAME;
                continue;
            }
            $ret_val[$row->INDEX_NAME] = new stdClass();
            $ret_val[$row->INDEX_NAME]->name = $row->INDEX_NAME;
            $ret_val[$row->INDEX_NAME]->fields = [$row->COLUMN_NAME];
            $ret_val[$row->INDEX_NAME]->type = $constraint_types[$row->CONSTRAINT_TYPE] ?? 'INDEX';
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
        $sql = 'SELECT
                acc.constraint_name,
                acc.table_name,
                acc.column_name,
                ccu.table_name foreign_table_name,
                accu.column_name foreign_column_name,
                ac.delete_rule
                FROM all_cons_columns acc
                JOIN all_constraints ac ON acc.owner = ac.owner
                AND acc.constraint_name = ac.constraint_name
                JOIN all_constraints ccu ON ac.r_owner = ccu.owner
                AND ac.r_constraint_name = ccu.constraint_name
                JOIN all_cons_columns accu ON accu.constraint_name = ccu.constraint_name
                AND accu.position = acc.position
                AND accu.table_name = ccu.table_name
                WHERE ac.constraint_type = ' . $this->escape('R') . '
                AND acc.table_name = ' . $this->escape($table);
        $query = $this->query($sql);
        if ($query === false) {
            throw new Database_Exception(lang('Database.failGetForeignKeyData'));
        }
        $query = $query->get_result_object();
        $indexes = [];
        foreach ($query as $row) {
            $indexes[$row->CONSTRAINT_NAME]['constraint_name'] = $row->CONSTRAINT_NAME;
            $indexes[$row->CONSTRAINT_NAME]['table_name'] = $row->TABLE_NAME;
            $indexes[$row->CONSTRAINT_NAME]['column_name'][] = $row->COLUMN_NAME;
            $indexes[$row->CONSTRAINT_NAME]['foreign_table_name'] = $row->FOREIGN_TABLE_NAME;
            $indexes[$row->CONSTRAINT_NAME]['foreign_column_name'][] = $row->FOREIGN_COLUMN_NAME;
            $indexes[$row->CONSTRAINT_NAME]['on_delete'] = $row->DELETE_RULE;
            $indexes[$row->CONSTRAINT_NAME]['on_update'] = null;
            $indexes[$row->CONSTRAINT_NAME]['match'] = null;
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
        return <<<'SQL'
        BEGIN
          FOR c IN
          (SELECT c.owner, c.table_name, c.constraint_name
           FROM user_constraints c, user_tables t
           WHERE c.table_name = t.table_name
           AND c.status = 'ENABLED'
           AND c.constraint_type = 'R'
           AND t.iot_type IS NULL
           ORDER BY c.constraint_type DESC)
          LOOP
            dbms_utility.exec_ddl_statement('alter table "' || c.owner || '"."' || c.table_name || '" disable constraint "' || c.constraint_name || '"');
          END LOOP;
        END;
        SQL;
    }
    /**
     * Returns platform-specific SQL to enable foreign key checks.
     *
     * @return string
     */
    protected function _enable_foreign_key_checks()
    {
        return <<<'SQL'
        BEGIN
          FOR c IN
          (SELECT c.owner, c.table_name, c.constraint_name
           FROM user_constraints c, user_tables t
           WHERE c.table_name = t.table_name
           AND c.status = 'DISABLED'
           AND c.constraint_type = 'R'
           AND t.iot_type IS NULL
           ORDER BY c.constraint_type DESC)
          LOOP
            dbms_utility.exec_ddl_statement('alter table "' || c.owner || '"."' || c.table_name || '" enable constraint "' || c.constraint_name || '"');
          END LOOP;
        END;
        SQL;
    }
    /**
     * Get cursor. Returns a cursor from the database
     *
     * @return resource
     */
    public function get_cursor()
    {
        return $this->cursor_id = oci_new_cursor($this->conn_id);
    }
    /**
     * Executes a stored procedure
     *
     * @param string $procedureName procedure name to execute
     * @param array  $params        params array keys
     *                              KEY      OPTIONAL  NOTES
     *                              name     no        the name of the parameter should be in :<param_name> format
     *                              value    no        the value of the parameter.  If this is an OUT or IN OUT parameter,
     *                              this should be a reference to a variable
     *                              type     yes       the type of the parameter
     *                              length   yes       the max size of the parameter
     *
     * @return bool|Query|Result
     */
    public function stored_procedure(string $procedure_name, array $params)
    {
        if ($procedure_name === '') {
            throw new Database_Exception(lang('Database.invalidArgument', [$procedure_name]));
        }
        // Build the query string
        $sql = sprintf('BEGIN %s (' . substr(str_repeat(',%s', count($params)), 1) . '); END;', $procedure_name, ...array_map(static fn($row) => $row['name'], $params));
        $this->reset_stmt_id = false;
        $this->stmt_id = oci_parse($this->conn_id, $sql);
        $this->bind_params($params);
        $result = $this->query($sql);
        $this->reset_stmt_id = true;
        return $result;
    }
    /**
     * Bind parameters
     *
     * @param array $params
     *
     * @return void
     */
    protected function bind_params($params)
    {
        if (!is_array($params) || !is_resource($this->stmt_id)) {
            return;
        }
        foreach ($params as $param) {
            oci_bind_by_name($this->stmt_id, $param['name'], $param['value'], $param['length'] ?? -1, $param['type'] ?? SQLT_CHR);
        }
    }
    /**
     * Returns the last error code and message.
     *
     * Must return an array with keys 'code' and 'message':
     *
     *  return ['code' => null, 'message' => null);
     */
    public function error(): array
    {
        // oci_error() returns an array that already contains
        // 'code' and 'message' keys, but it can return false
        // if there was no error ....
        $error = oci_error();
        $resources = [$this->cursor_id, $this->stmt_id, $this->conn_id];
        foreach ($resources as $resource) {
            if (is_resource($resource)) {
                $error = oci_error($resource);
                break;
            }
        }
        return is_array($error) ? $error : ['code' => '', 'message' => ''];
    }
    public function insert_id(): int
    {
        if (empty($this->last_inserted_table_name)) {
            return 0;
        }
        $indexs = $this->get_index_data($this->last_inserted_table_name);
        $field_datas = $this->get_field_data($this->last_inserted_table_name);
        if ($indexs === [] || $field_datas === []) {
            return 0;
        }
        $column_type_list = array_column($field_datas, 'type', 'name');
        $primary_column_name = '';
        foreach ($indexs as $index) {
            if ($index->type !== 'PRIMARY' || count($index->fields) !== 1) {
                continue;
            }
            $primary_column_name = $this->protect_identifiers($index->fields[0], false, false);
            $primary_column_type = $column_type_list[$primary_column_name];
            if ($primary_column_type !== 'NUMBER') {
                $primary_column_name = '';
            }
        }
        if ($primary_column_name === '') {
            return 0;
        }
        $query = $this->query('SELECT DATA_DEFAULT FROM USER_TAB_COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?', [$this->last_inserted_table_name, $primary_column_name])->get_row();
        $last_insert_value = str_replace('nextval', 'currval', $query->DATA_DEFAULT ?? '0');
        $query = $this->query(sprintf('SELECT %s SEQ FROM DUAL', $last_insert_value))->get_row();
        return (int) ($query->SEQ ?? 0);
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
        // Legacy support for TNS in the hostname configuration field
        $this->hostname = str_replace(["\n", "\r", "\t", ' '], '', $this->hostname);
        if (preg_match($this->valid_ds_ns['tns'], $this->hostname)) {
            $this->DSN = $this->hostname;
            return;
        }
        $is_easy_connectable_host_name = $this->hostname !== '' && !str_contains($this->hostname, '/') && !str_contains($this->hostname, ':');
        $easy_connectable_port = $this->port !== '' && ctype_digit((string) $this->port) ? ':' . $this->port : '';
        $easy_connectable_database = $this->database !== '' ? '/' . ltrim($this->database, '/') : '';
        if ($is_easy_connectable_host_name && ($easy_connectable_port !== '' || $easy_connectable_database !== '')) {
            /* If the hostname field isn't empty, doesn't contain
             * ':' and/or '/' and if port and/or database aren't
             * empty, then the hostname field is most likely indeed
             * just a hostname. Therefore we'll try and build an
             * Easy Connect string from these 3 settings, assuming
             * that the database field is a service name.
             */
            $this->DSN = $this->hostname . $easy_connectable_port . $easy_connectable_database;
            if (preg_match($this->valid_ds_ns['ec'], $this->DSN)) {
                return;
            }
        }
        /* At this point, we can only try and validate the hostname and
         * database fields separately as DSNs.
         */
        if (preg_match($this->valid_ds_ns['ec'], $this->hostname) || preg_match($this->valid_ds_ns['in'], $this->hostname)) {
            $this->DSN = $this->hostname;
            return;
        }
        $this->database = str_replace(["\n", "\r", "\t", ' '], '', $this->database);
        foreach ($this->valid_ds_ns as $regexp) {
            if (preg_match($regexp, $this->database)) {
                return;
            }
        }
        /* Well - OK, an empty string should work as well.
         * PHP will try to use environment variables to
         * determine which Oracle instance to connect to.
         */
        $this->DSN = '';
    }
    /**
     * Begin Transaction
     */
    protected function _trans_begin(): bool
    {
        $this->commit_mode = OCI_NO_AUTO_COMMIT;
        return true;
    }
    /**
     * Commit Transaction
     */
    protected function _trans_commit(): bool
    {
        $this->commit_mode = OCI_COMMIT_ON_SUCCESS;
        return oci_commit($this->conn_id);
    }
    /**
     * Rollback Transaction
     */
    protected function _trans_rollback(): bool
    {
        $this->commit_mode = OCI_COMMIT_ON_SUCCESS;
        return oci_rollback($this->conn_id);
    }
    /**
     * Returns the name of the current database being used.
     */
    public function get_database(): string
    {
        if (!empty($this->database)) {
            return $this->database;
        }
        return $this->query('SELECT DEFAULT_TABLESPACE FROM USER_USERS')->get_row()->DEFAULT_TABLESPACE ?? '';
    }
    /**
     * Get the prefix of the function to access the DB.
     */
    protected function get_driver_function_prefix(): string
    {
        return 'oci_';
    }
}