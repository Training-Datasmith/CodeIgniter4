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
namespace Code_Igniter\Database\Sq_Lite3;

use Code_Igniter\Database\Base_Connection;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Database\Table_Name;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Exception;
use Sq_Lite3;
use Sq_Lite3result;
use stdClass;
/**
 * Connection for SQLite3
 *
 * @extends BaseConnection<SQLite3, SQLite3Result>
 */
class Connection extends Base_Connection
{
    /**
     * Database driver
     *
     * @var string
     */
    public $db_driver = 'SQLite3';
    /**
     * Identifier escape character
     *
     * @var string
     */
    public $escape_char = '`';
    /**
     * @var bool Enable Foreign Key constraint or not
     */
    protected $foreign_keys = false;
    /**
     * The milliseconds to sleep
     *
     * @var int|null milliseconds
     *
     * @see https://www.php.net/manual/en/sqlite3.busytimeout
     */
    protected $busy_timeout;
    /**
     * The setting of the "synchronous" flag
     *
     * @var int<0, 3>|null flag
     *
     * @see https://www.sqlite.org/pragma.html#pragma_synchronous
     */
    protected ?int $synchronous = null;
    /**
     * @return void
     */
    public function initialize()
    {
        parent::initialize();
        if ($this->foreign_keys) {
            $this->enable_foreign_key_checks();
        }
        if (is_int($this->busy_timeout)) {
            $this->conn_id->busy_timeout($this->busy_timeout);
        }
        if (is_int($this->synchronous)) {
            if (!in_array($this->synchronous, [0, 1, 2, 3], true)) {
                throw new InvalidArgumentException('Invalid synchronous value.');
            }
            $this->conn_id->exec('PRAGMA synchronous = ' . $this->synchronous);
        }
    }
    /**
     * Connect to the database.
     *
     * @return SQLite3
     *
     * @throws DatabaseException
     */
    public function connect(bool $persistent = false)
    {
        if ($persistent && $this->db_debug) {
            throw new Database_Exception('SQLite3 doesn\'t support persistent connections.');
        }
        try {
            if ($this->database !== ':memory:' && !str_contains($this->database, DIRECTORY_SEPARATOR)) {
                $this->database = WRITEPATH . $this->database;
            }
            $sqlite = $this->password === null || $this->password === '' ? new Sq_Lite3($this->database) : new Sq_Lite3($this->database, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, $this->password);
            $sqlite->enable_exceptions(true);
            return $sqlite;
        } catch (Exception $e) {
            throw new Database_Exception('SQLite3 error: ' . $e->get_message(), $e->get_code(), $e);
        }
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
        $version = Sq_Lite3::version();
        return $this->data_cache['version'] = $version['versionString'];
    }
    /**
     * Execute the query
     *
     * @return false|SQLite3Result
     */
    protected function execute(string $sql)
    {
        try {
            return $this->is_write_type($sql) ? $this->conn_id->exec($sql) : $this->conn_id->query($sql);
        } catch (Exception $e) {
            log_message('error', "{message}\nin {exFile} on line {exLine}.\n{trace}", ['message' => $e->get_message(), 'exFile' => clean_path($e->get_file()), 'exLine' => $e->get_line(), 'trace' => render_backtrace($e->get_trace())]);
            if ($this->db_debug) {
                throw new Database_Exception($e->get_message(), $e->get_code(), $e);
            }
        }
        return false;
    }
    /**
     * Returns the total number of rows affected by this query.
     */
    public function affected_rows(): int
    {
        return $this->conn_id->changes();
    }
    /**
     * Platform-dependant string escape
     */
    protected function _escape_string(string $str): string
    {
        if (!$this->conn_id instanceof Sq_Lite3) {
            $this->initialize();
        }
        return $this->conn_id->escape_string($str);
    }
    /**
     * Generates the SQL for listing tables in a platform-dependent manner.
     *
     * @param string|null $tableName If $tableName is provided will return only this table if exists.
     */
    protected function _list_tables(bool $prefix_limit = false, ?string $table_name = null): string
    {
        if ((string) $table_name !== '') {
            return 'SELECT "NAME" FROM "SQLITE_MASTER" WHERE "TYPE" = \'table\'' . ' AND "NAME" NOT LIKE \'sqlite!_%\' ESCAPE \'!\'' . ' AND "NAME" LIKE ' . $this->escape($table_name);
        }
        return 'SELECT "NAME" FROM "SQLITE_MASTER" WHERE "TYPE" = \'table\'' . ' AND "NAME" NOT LIKE \'sqlite!_%\' ESCAPE \'!\'' . ($prefix_limit && $this->db_prefix !== '' ? ' AND "NAME" LIKE \'' . $this->escape_like_string($this->db_prefix) . '%\' ' . sprintf($this->like_escape_str, $this->like_escape_char) : '');
    }
    /**
     * Generates a platform-specific query string so that the column names can be fetched.
     *
     * @param string|TableName $table
     */
    protected function _list_columns($table = ''): string
    {
        if ($table instanceof Table_Name) {
            $table_name = $this->escape_identifier($table);
        } else {
            $table_name = $this->protect_identifiers($table, true, null, false);
        }
        return 'PRAGMA TABLE_INFO(' . $table_name . ')';
    }
    /**
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
        if (!$this->conn_id instanceof Sq_Lite3) {
            $this->initialize();
        }
        $sql = $this->_list_columns($table_name);
        $query = $this->query($sql);
        $this->data_cache['field_names'][$table] = [];
        foreach ($query->get_result_array() as $row) {
            // Do we know from where to get the column's name?
            if (!isset($key)) {
                if (isset($row['column_name'])) {
                    $key = 'column_name';
                } elseif (isset($row['COLUMN_NAME'])) {
                    $key = 'COLUMN_NAME';
                } elseif (isset($row['name'])) {
                    $key = 'name';
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
     * Returns an array of objects with field data
     *
     * @return list<stdClass>
     *
     * @throws DatabaseException
     */
    protected function _field_data(string $table): array
    {
        if (false === $query = $this->query('PRAGMA TABLE_INFO(' . $this->protect_identifiers($table, true, null, false) . ')')) {
            throw new Database_Exception(lang('Database.failGetFieldData'));
        }
        $query = $query->get_result_object();
        if (empty($query)) {
            return [];
        }
        $ret_val = [];
        for ($i = 0, $c = count($query); $i < $c; $i++) {
            $ret_val[$i] = new stdClass();
            $ret_val[$i]->name = $query[$i]->name;
            $ret_val[$i]->type = $query[$i]->type;
            $ret_val[$i]->max_length = null;
            $ret_val[$i]->nullable = isset($query[$i]->notnull) && !(bool) $query[$i]->notnull;
            $ret_val[$i]->default = $query[$i]->dflt_value;
            // "pk" (either zero for columns that are not part of the primary key,
            // or the 1-based index of the column within the primary key).
            // https://www.sqlite.org/pragma.html#pragma_table_info
            $ret_val[$i]->primary_key = $query[$i]->pk === 0 ? 0 : 1;
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
        $sql = "SELECT 'PRIMARY' as indexname, l.name as fieldname, 'PRIMARY' as indextype\n                FROM pragma_table_info(" . $this->escape(strtolower($table)) . ") as l\n                WHERE l.pk <> 0\n                UNION ALL\n                SELECT sqlite_master.name as indexname, ii.name as fieldname,\n                CASE\n                WHEN ti.pk <> 0 AND sqlite_master.name LIKE 'sqlite_autoindex_%' THEN 'PRIMARY'\n                WHEN sqlite_master.name LIKE 'sqlite_autoindex_%' THEN 'UNIQUE'\n                WHEN sqlite_master.sql LIKE '% UNIQUE %' THEN 'UNIQUE'\n                ELSE 'INDEX'\n                END as indextype\n                FROM sqlite_master\n                INNER JOIN pragma_index_xinfo(sqlite_master.name) ii ON ii.name IS NOT NULL\n                LEFT JOIN pragma_table_info(" . $this->escape(strtolower($table)) . ") ti ON ti.name = ii.name\n                WHERE sqlite_master.type='index' AND sqlite_master.tbl_name = " . $this->escape(strtolower($table)) . ' COLLATE NOCASE';
        if (($query = $this->query($sql)) === false) {
            throw new Database_Exception(lang('Database.failGetIndexData'));
        }
        $query = $query->get_result_object();
        $temp_val = [];
        foreach ($query as $row) {
            if ($row->indextype === 'PRIMARY') {
                $temp_val['PRIMARY']['indextype'] = $row->indextype;
                $temp_val['PRIMARY']['indexname'] = $row->indexname;
                $temp_val['PRIMARY']['fields'][$row->fieldname] = $row->fieldname;
            } else {
                $temp_val[$row->indexname]['indextype'] = $row->indextype;
                $temp_val[$row->indexname]['indexname'] = $row->indexname;
                $temp_val[$row->indexname]['fields'][$row->fieldname] = $row->fieldname;
            }
        }
        $ret_val = [];
        foreach ($temp_val as $val) {
            $obj = new stdClass();
            $obj->name = $val['indexname'];
            $obj->fields = array_values($val['fields']);
            $obj->type = $val['indextype'];
            $ret_val[$obj->name] = $obj;
        }
        return $ret_val;
    }
    /**
     * Returns an array of objects with Foreign key data
     *
     * @return array<string, stdClass>
     */
    protected function _foreign_key_data(string $table): array
    {
        if (!$this->supports_foreign_keys()) {
            return [];
        }
        $query = $this->query("PRAGMA foreign_key_list({$table})")->get_result();
        $indexes = [];
        foreach ($query as $row) {
            $indexes[$row->id]['constraint_name'] = null;
            $indexes[$row->id]['table_name'] = $table;
            $indexes[$row->id]['foreign_table_name'] = $row->table;
            $indexes[$row->id]['column_name'][] = $row->from;
            $indexes[$row->id]['foreign_column_name'][] = $row->to;
            $indexes[$row->id]['on_delete'] = $row->on_delete;
            $indexes[$row->id]['on_update'] = $row->on_update;
            $indexes[$row->id]['match'] = $row->match;
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
        return 'PRAGMA foreign_keys = OFF';
    }
    /**
     * Returns platform-specific SQL to enable foreign key checks.
     *
     * @return string
     */
    protected function _enable_foreign_key_checks()
    {
        return 'PRAGMA foreign_keys = ON';
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
        return ['code' => $this->conn_id->last_error_code(), 'message' => $this->conn_id->last_error_msg()];
    }
    /**
     * Insert ID
     */
    public function insert_id(): int
    {
        return $this->conn_id->last_insert_row_id();
    }
    /**
     * Begin Transaction
     */
    protected function _trans_begin(): bool
    {
        return $this->conn_id->exec('BEGIN TRANSACTION');
    }
    /**
     * Commit Transaction
     */
    protected function _trans_commit(): bool
    {
        return $this->conn_id->exec('END TRANSACTION');
    }
    /**
     * Rollback Transaction
     */
    protected function _trans_rollback(): bool
    {
        return $this->conn_id->exec('ROLLBACK');
    }
    /**
     * Checks to see if the current install supports Foreign Keys
     * and has them enabled.
     */
    public function supports_foreign_keys(): bool
    {
        $result = $this->simple_query('PRAGMA foreign_keys');
        return (bool) $result;
    }
}