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

use Code_Igniter\Database\Base_Builder;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Database\Exceptions\Data_Exception;
use Code_Igniter\Database\Raw_Sql;
use Code_Igniter\Database\Result_Interface;
use Config\Feature;
/**
 * Builder for SQLSRV
 *
 * @todo auto check for TextCastToInt
 * @todo auto check for InsertIndexValue
 * @todo replace: delete index entries before insert
 */
class Builder extends Base_Builder
{
    /**
     * ORDER BY random keyword
     *
     * @var array
     */
    protected $random_keyword = ['NEWID()', 'RAND(%d)'];
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
     * Handle increment/decrement on text
     *
     * @var bool
     */
    public $cast_text_to_int = true;
    /**
     * Handle IDENTITY_INSERT property/
     *
     * @var bool
     */
    public $key_permission = false;
    /**
     * Groups tables in FROM clauses if needed, so there is no confusion
     * about operator precedence.
     */
    protected function _from_tables(): string
    {
        $from = [];
        foreach ($this->qb_from as $value) {
            $from[] = str_starts_with($value, '(SELECT') ? $value : $this->get_full_name($value);
        }
        return implode(', ', $from);
    }
    /**
     * Generates a platform-specific truncate string from the supplied data
     *
     * If the database does not support the truncate() command,
     * then this method maps to 'DELETE FROM table'
     */
    protected function _truncate(string $table): string
    {
        return 'TRUNCATE TABLE ' . $this->get_full_name($table);
    }
    /**
     * Generates the JOIN portion of the query
     *
     * @param RawSql|string $cond
     *
     * @return $this
     */
    public function join(string $table, $cond, string $type = '', ?bool $escape = null)
    {
        if ($type !== '') {
            $type = strtoupper(trim($type));
            if (!in_array($type, $this->join_types, true)) {
                $type = '';
            } else {
                $type .= ' ';
            }
        }
        // Extract any aliases that might exist. We use this information
        // in the protectIdentifiers to know whether to add a table prefix
        $this->track_aliases($table);
        if (!is_bool($escape)) {
            $escape = $this->db->protect_identifiers;
        }
        if (!$this->has_operator($cond)) {
            $cond = ' USING (' . ($escape ? $this->db->escape_identifiers($cond) : $cond) . ')';
        } elseif ($escape === false) {
            $cond = ' ON ' . $cond;
        } else {
            // Split multiple conditions
            if (preg_match_all('/\sAND\s|\sOR\s/i', $cond, $joints, PREG_OFFSET_CAPTURE) >= 1) {
                $conditions = [];
                $joints = $joints[0];
                array_unshift($joints, ['', 0]);
                for ($i = count($joints) - 1, $pos = strlen($cond); $i >= 0; $i--) {
                    $joints[$i][1] += strlen($joints[$i][0]);
                    // offset
                    $conditions[$i] = substr($cond, $joints[$i][1], $pos - $joints[$i][1]);
                    $pos = $joints[$i][1] - strlen($joints[$i][0]);
                    $joints[$i] = $joints[$i][0];
                }
                ksort($conditions);
            } else {
                $conditions = [$cond];
                $joints = [''];
            }
            $cond = ' ON ';
            foreach ($conditions as $i => $condition) {
                $operator = $this->get_operator($condition);
                // Workaround for BETWEEN
                if ($operator === false) {
                    $cond .= $joints[$i] . $condition;
                    continue;
                }
                $cond .= $joints[$i];
                $cond .= preg_match('/(\(*)?([\[\]\w\.\'-]+)' . preg_quote($operator, '/') . '(.*)/i', $condition, $match) ? $match[1] . $this->db->protect_identifiers($match[2]) . $operator . $this->db->protect_identifiers($match[3]) : $condition;
            }
        }
        // Do we want to escape the table name?
        if ($escape === true) {
            $table = $this->db->protect_identifiers($table, true, null, false);
        }
        // Assemble the JOIN statement
        $this->qb_join[] = $type . 'JOIN ' . $this->get_full_name($table) . $cond;
        return $this;
    }
    /**
     * Generates a platform-specific insert string from the supplied data
     *
     * @todo implement check for this instead static $insertKeyPermission
     */
    protected function _insert(string $table, array $keys, array $unescaped_keys): string
    {
        $full_table_name = $this->get_full_name($table);
        // insert statement
        $statement = 'INSERT INTO ' . $full_table_name . ' (' . implode(',', $keys) . ') VALUES (' . implode(', ', $unescaped_keys) . ')';
        return $this->key_permission ? $this->add_identity($full_table_name, $statement) : $statement;
    }
    /**
     * Insert batch statement
     *
     * Generates a platform-specific insert string from the supplied data.
     */
    protected function _insert_batch(string $table, array $keys, array $values): string
    {
        $sql = $this->qb_options['sql'] ?? '';
        // if this is the first iteration of batch then we need to build skeleton sql
        if ($sql === '') {
            $sql = 'INSERT ' . $this->compile_ignore('insert') . 'INTO ' . $this->get_full_name($table) . ' (' . implode(', ', $keys) . ")\n{:_table_:}";
            $this->qb_options['sql'] = $sql;
        }
        if (isset($this->qb_options['setQueryAsData'])) {
            $data = $this->qb_options['setQueryAsData'];
        } else {
            $data = 'VALUES ' . implode(', ', $this->format_values($values));
        }
        return str_replace('{:_table_:}', $data, $sql);
    }
    /**
     * Generates a platform-specific update string from the supplied data
     */
    protected function _update(string $table, array $values): string
    {
        $valstr = [];
        foreach ($values as $key => $val) {
            $valstr[] = $key . ' = ' . $val;
        }
        $full_table_name = $this->get_full_name($table);
        $statement = sprintf('UPDATE %s%s SET ', empty($this->qb_limit) ? '' : 'TOP(' . $this->qb_limit . ') ', $full_table_name);
        $statement .= implode(', ', $valstr) . $this->compile_where_having('QBWhere') . $this->compile_order_by();
        return $this->key_permission ? $this->add_identity($full_table_name, $statement) : $statement;
    }
    /**
     * Increments a numeric column by the specified value.
     *
     * @return bool
     */
    public function increment(string $column, int $value = 1)
    {
        $column = $this->db->protect_identifiers($column);
        if ($this->cast_text_to_int) {
            $values = [$column => "CONVERT(VARCHAR(MAX),CONVERT(INT,CONVERT(VARCHAR(MAX), {$column})) + {$value})"];
        } else {
            $values = [$column => "{$column} + {$value}"];
        }
        $sql = $this->_update($this->qb_from[0], $values);
        if (!$this->test_mode) {
            $this->reset_write();
            return $this->db->query($sql, $this->binds, false);
        }
        return true;
    }
    /**
     * Decrements a numeric column by the specified value.
     *
     * @return bool
     */
    public function decrement(string $column, int $value = 1)
    {
        $column = $this->db->protect_identifiers($column);
        if ($this->cast_text_to_int) {
            $values = [$column => "CONVERT(VARCHAR(MAX),CONVERT(INT,CONVERT(VARCHAR(MAX), {$column})) - {$value})"];
        } else {
            $values = [$column => "{$column} + {$value}"];
        }
        $sql = $this->_update($this->qb_from[0], $values);
        if (!$this->test_mode) {
            $this->reset_write();
            return $this->db->query($sql, $this->binds, false);
        }
        return true;
    }
    /**
     * Get full name of the table
     */
    private function get_full_name(string $table): string
    {
        $alias = '';
        if (str_contains($table, ' ')) {
            $alias = explode(' ', $table);
            $table = array_shift($alias);
            $alias = ' ' . implode(' ', $alias);
        }
        if ($this->db->escape_char === '"') {
            if (str_contains($table, '.') && !str_starts_with($table, '.') && !str_ends_with($table, '.')) {
                $db_info = explode('.', $table);
                $database = $this->db->get_database();
                $table = $db_info[0];
                if (count($db_info) === 3) {
                    $database = str_replace('"', '', $db_info[0]);
                    $schema = str_replace('"', '', $db_info[1]);
                    $table_name = str_replace('"', '', $db_info[2]);
                } else {
                    $schema = str_replace('"', '', $db_info[0]);
                    $table_name = str_replace('"', '', $db_info[1]);
                }
                return '"' . $database . '"."' . $schema . '"."' . str_replace('"', '', $table_name) . '"' . $alias;
            }
            return '"' . $this->db->get_database() . '"."' . $this->db->schema . '"."' . str_replace('"', '', $table) . '"' . $alias;
        }
        return '[' . $this->db->get_database() . '].[' . $this->db->schema . '].[' . str_replace('"', '', $table) . ']' . str_replace('"', '', $alias);
    }
    /**
     * Add permision statements for index value inserts
     */
    private function add_identity(string $full_table, string $insert): string
    {
        return 'SET IDENTITY_INSERT ' . $full_table . " ON\n" . $insert . "\nSET IDENTITY_INSERT " . $full_table . ' OFF';
    }
    /**
     * Local implementation of limit
     */
    protected function _limit(string $sql, bool $offset_ignore = false): string
    {
        // SQL Server cannot handle `LIMIT 0`.
        // DatabaseException:
        //   [Microsoft][ODBC Driver 17 for SQL Server][SQL Server]The number of
        //   rows provided for a FETCH clause must be greater then zero.
        $limit_zero_as_all = config(Feature::class)->limit_zero_as_all ?? true;
        if (!$limit_zero_as_all && $this->qb_limit === 0) {
            return "SELECT * \nFROM " . $this->_from_tables() . ' WHERE 1=0 ';
        }
        if (empty($this->qb_order_by)) {
            $sql .= ' ORDER BY (SELECT NULL) ';
        }
        if ($offset_ignore) {
            $sql .= ' OFFSET 0 ';
        } else {
            $sql .= is_int($this->qb_offset) ? ' OFFSET ' . $this->qb_offset : ' OFFSET 0 ';
        }
        return $sql . ' ROWS FETCH NEXT ' . $this->qb_limit . ' ROWS ONLY ';
    }
    /**
     * Compiles a replace into string and runs the query
     *
     * @return mixed
     *
     * @throws DatabaseException
     */
    public function replace(?array $set = null)
    {
        if ($set !== null) {
            $this->set($set);
        }
        if ($this->qb_set === []) {
            if ($this->db->db_debug) {
                throw new Database_Exception('You must use the "set" method to update an entry.');
            }
            return false;
            // @codeCoverageIgnore
        }
        $table = $this->qb_from[0];
        $sql = $this->_replace($table, array_keys($this->qb_set), array_values($this->qb_set));
        $this->reset_write();
        if ($this->test_mode) {
            return $sql;
        }
        $this->db->simple_query('SET IDENTITY_INSERT ' . $this->get_full_name($table) . ' ON');
        $result = $this->db->query($sql, $this->binds, false);
        $this->db->simple_query('SET IDENTITY_INSERT ' . $this->get_full_name($table) . ' OFF');
        return $result;
    }
    /**
     * Generates a platform-specific replace string from the supplied data
     * on match delete and insert
     */
    protected function _replace(string $table, array $keys, array $values): string
    {
        // check whether the existing keys are part of the primary key.
        // if so then use them for the "ON" part and exclude them from the $values and $keys
        $p_keys = $this->db->get_index_data($table);
        $key_fields = [];
        foreach ($p_keys as $key) {
            if ($key->type === 'PRIMARY') {
                $key_fields = array_merge($key_fields, $key->fields);
            }
            if ($key->type === 'UNIQUE') {
                $key_fields = array_merge($key_fields, $key->fields);
            }
        }
        // Get the unique field names
        $esc_key_fields = array_map(fn(string $field): string => $this->db->protect_identifiers($field), array_values(array_unique($key_fields)));
        // Get the binds
        $binds = $this->binds;
        array_walk($binds, static function (&$item): void {
            $item = $item[0];
        });
        // Get the common field and values from the keys data and index fields
        $common = array_intersect($keys, $esc_key_fields);
        $bingo = [];
        foreach ($common as $v) {
            $k = array_search($v, $keys, true);
            $bingo[$keys[$k]] = $binds[trim($values[$k], ':')];
        }
        // Querying existing data
        $builder = $this->db->table($table);
        foreach ($bingo as $k => $v) {
            $builder->where($k, $v);
        }
        $q = $builder->get()->get_result();
        // Delete entries if we find them
        if ($q !== []) {
            $delete = $this->db->table($table);
            foreach ($bingo as $k => $v) {
                $delete->where($k, $v);
            }
            $delete->delete();
        }
        return sprintf('INSERT INTO %s (%s) VALUES (%s);', $this->get_full_name($table), implode(',', $keys), implode(',', $values));
    }
    /**
     * SELECT [MAX|MIN|AVG|SUM|COUNT]()
     *
     * Handle float return value
     *
     * @return BaseBuilder
     */
    protected function max_min_avg_sum(string $select = '', string $alias = '', string $type = 'MAX')
    {
        // int functions can be handled by parent
        if ($type !== 'AVG') {
            return parent::max_min_avg_sum($select, $alias, $type);
        }
        if ($select === '') {
            throw Data_Exception::for_empty_input_given('Select');
        }
        if (str_contains($select, ',')) {
            throw Data_Exception::for_invalid_argument('Column name not separated by comma');
        }
        if ($alias === '') {
            $alias = $this->create_alias_from_table(trim($select));
        }
        $sql = $type . '( CAST( ' . $this->db->protect_identifiers(trim($select)) . ' AS FLOAT ) ) AS ' . $this->db->escape_identifiers(trim($alias));
        $this->qb_select[] = $sql;
        $this->qb_no_escape[] = null;
        return $this;
    }
    /**
     * "Count All" query
     *
     * Generates a platform-specific query string that counts all records in
     * the particular table
     *
     * @param bool $reset Are we want to clear query builder values?
     *
     * @return int|string when $test = true
     */
    public function count_all(bool $reset = true)
    {
        $table = $this->qb_from[0];
        $sql = $this->count_string . $this->db->escape_identifiers('numrows') . ' FROM ' . $this->get_full_name($table);
        if ($this->test_mode) {
            return $sql;
        }
        $query = $this->db->query($sql, null, false);
        if (empty($query->get_result())) {
            return 0;
        }
        $query = $query->get_row();
        if ($reset) {
            $this->reset_select();
        }
        return (int) $query->numrows;
    }
    /**
     * Delete statement
     */
    protected function _delete(string $table): string
    {
        return 'DELETE' . (empty($this->qb_limit) ? '' : ' TOP (' . $this->qb_limit . ') ') . ' FROM ' . $this->get_full_name($table) . $this->compile_where_having('QBWhere');
    }
    /**
     * Compiles a delete string and runs the query
     *
     * @param mixed $where
     *
     * @return mixed
     *
     * @throws DatabaseException
     */
    public function delete($where = '', ?int $limit = null, bool $reset_data = true)
    {
        $table = $this->db->protect_identifiers($this->qb_from[0], true, null, false);
        if ($where !== '') {
            $this->where($where);
        }
        if ($this->qb_where === []) {
            if ($this->db->db_debug) {
                throw new Database_Exception('Deletes are not allowed unless they contain a "where" or "like" clause.');
            }
            return false;
            // @codeCoverageIgnore
        }
        if ($limit !== null && $limit !== 0) {
            $this->qb_limit = $limit;
        }
        $sql = $this->_delete($table);
        if ($reset_data) {
            $this->reset_write();
        }
        return $this->test_mode ? $sql : $this->db->query($sql, $this->binds, false);
    }
    /**
     * Compile the SELECT statement
     *
     * Generates a query string based on which functions were used.
     *
     * @param bool $selectOverride
     */
    protected function compile_select($select_override = false): string
    {
        // Write the "select" portion of the query
        if ($select_override !== false) {
            $sql = $select_override;
        } else {
            $sql = $this->qb_distinct ? 'SELECT DISTINCT ' : 'SELECT ';
            // SQL Server can't work with select * if group by is specified
            if (empty($this->qb_select) && $this->qb_group_by !== [] && is_array($this->qb_group_by)) {
                foreach ($this->qb_group_by as $field) {
                    $this->qb_select[] = is_array($field) ? $field['field'] : $field;
                }
            }
            if (empty($this->qb_select)) {
                $sql .= '*';
            } else {
                // Cycle through the "select" portion of the query and prep each column name.
                // The reason we protect identifiers here rather than in the select() function
                // is because until the user calls the from() function we don't know if there are aliases
                foreach ($this->qb_select as $key => $val) {
                    $no_escape = $this->qb_no_escape[$key] ?? null;
                    $this->qb_select[$key] = $this->db->protect_identifiers($val, false, $no_escape);
                }
                $sql .= implode(', ', $this->qb_select);
            }
        }
        // Write the "FROM" portion of the query
        if ($this->qb_from !== []) {
            $sql .= "\nFROM " . $this->_from_tables();
        }
        // Write the "JOIN" portion of the query
        if (!empty($this->qb_join)) {
            $sql .= "\n" . implode("\n", $this->qb_join);
        }
        $sql .= $this->compile_where_having('QBWhere') . $this->compile_group_by() . $this->compile_where_having('QBHaving') . $this->compile_order_by();
        // ORDER BY
        // LIMIT
        $limit_zero_as_all = config(Feature::class)->limit_zero_as_all ?? true;
        if ($limit_zero_as_all) {
            if ($this->qb_limit) {
                $sql = $this->_limit($sql . "\n");
            }
        } elseif ($this->qb_limit !== false || $this->qb_offset) {
            $sql = $this->_limit($sql . "\n");
        }
        return $this->union_injection($sql);
    }
    /**
     * Compiles the select statement based on the other functions called
     * and runs the query
     *
     * @return ResultInterface
     */
    public function get(?int $limit = null, int $offset = 0, bool $reset = true)
    {
        $limit_zero_as_all = config(Feature::class)->limit_zero_as_all ?? true;
        if ($limit_zero_as_all && $limit === 0) {
            $limit = null;
        }
        if ($limit !== null) {
            $this->limit($limit, $offset);
        }
        $result = $this->test_mode ? $this->get_compiled_select($reset) : $this->db->query($this->compile_select(), $this->binds, false);
        if ($reset) {
            $this->reset_select();
            // Clear our binds so we don't eat up memory
            $this->binds = [];
        }
        return $result;
    }
    /**
     * Generates a platform-specific upsertBatch string from the supplied data
     *
     * @throws DatabaseException
     */
    protected function _upsert_batch(string $table, array $keys, array $values): string
    {
        $sql = $this->qb_options['sql'] ?? '';
        // if this is the first iteration of batch then we need to build skeleton sql
        if ($sql === '') {
            $full_table_name = $this->get_full_name($table);
            $constraints = $this->qb_options['constraints'] ?? [];
            $table_identity = $this->qb_options['tableIdentity'] ?? '';
            $sql = "SELECT name from syscolumns where id = Object_ID('" . $table . "') and colstat = 1";
            if (($query = $this->db->query($sql)) === false) {
                throw new Database_Exception('Failed to get table identity');
            }
            $query = $query->get_result_object();
            foreach ($query as $row) {
                $table_identity = '"' . $row->name . '"';
            }
            $this->qb_options['tableIdentity'] = $table_identity;
            $identity_in_fields = in_array($table_identity, $keys, true);
            $field_names = array_map(static fn($column_name): string => trim($column_name, '"'), $keys);
            if (empty($constraints)) {
                $table_indexes = $this->db->get_index_data($table);
                $unique_indexes = array_filter($table_indexes, static function ($index) use ($field_names): bool {
                    $has_all_fields = count(array_intersect($index->fields, $field_names)) === count($index->fields);
                    return $index->type === 'PRIMARY' && $has_all_fields;
                });
                // if no primary found then look for unique - since indexes have no order
                if ($unique_indexes === []) {
                    $unique_indexes = array_filter($table_indexes, static function ($index) use ($field_names): bool {
                        $has_all_fields = count(array_intersect($index->fields, $field_names)) === count($index->fields);
                        return $index->type === 'UNIQUE' && $has_all_fields;
                    });
                }
                // only take first index
                foreach ($unique_indexes as $index) {
                    $constraints = $index->fields;
                    break;
                }
                $constraints = $this->on_constraint($constraints)->qb_options['constraints'] ?? [];
            }
            if (empty($constraints)) {
                if ($this->db->db_debug) {
                    throw new Database_Exception('No constraint found for upsert.');
                }
                return '';
                // @codeCoverageIgnore
            }
            $alias = $this->qb_options['alias'] ?? '"_upsert"';
            $update_fields = $this->qb_options['updateFields'] ?? $this->update_fields($keys, false, $constraints)->qb_options['updateFields'] ?? [];
            $sql = 'MERGE INTO ' . $full_table_name . "\nUSING (\n";
            $sql .= '{:_table_:}';
            $sql .= ") {$alias} (";
            $sql .= implode(', ', $keys);
            $sql .= ')';
            $sql .= "\nON (";
            $sql .= implode(' AND ', array_map(static fn($key, $value) => $value instanceof Raw_Sql && is_string($key) ? $full_table_name . '.' . $key . ' = ' . $value : ($value instanceof Raw_Sql ? $value : $full_table_name . '.' . $value . ' = ' . $alias . '.' . $value), array_keys($constraints), $constraints)) . ")\n";
            $sql .= "WHEN MATCHED THEN UPDATE SET\n";
            $sql .= implode(",\n", array_map(static fn($key, $value): string => $key . ($value instanceof Raw_Sql ? ' = ' . $value : " = {$alias}.{$value}"), array_keys($update_fields), $update_fields));
            $sql .= "\nWHEN NOT MATCHED THEN INSERT (" . implode(', ', $keys) . ")\nVALUES ";
            $sql .= '(' . implode(', ', array_map(static fn($column_name): string => $column_name === $table_identity ? "CASE WHEN {$alias}.{$column_name} IS NULL THEN (SELECT " . 'isnull(IDENT_CURRENT(\'' . $full_table_name . '\')+IDENT_INCR(\'' . $full_table_name . "'),1)) ELSE {$alias}.{$column_name} END" : "{$alias}.{$column_name}", $keys)) . ');';
            $sql = $identity_in_fields ? $this->add_identity($full_table_name, $sql) : $sql;
            $this->qb_options['sql'] = $sql;
        }
        if (isset($this->qb_options['setQueryAsData'])) {
            $data = $this->qb_options['setQueryAsData'];
        } else {
            $data = 'VALUES ' . implode(', ', $this->format_values($values)) . "\n";
        }
        return str_replace('{:_table_:}', $data, $sql);
    }
    /**
     * Gets column names from a select query
     */
    protected function fields_from_query(string $sql): array
    {
        return $this->db->query('SELECT TOP 1 * FROM (' . $sql . ') _u_')->get_field_names();
    }
}