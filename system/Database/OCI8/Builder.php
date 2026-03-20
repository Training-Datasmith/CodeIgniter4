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

use Code_Igniter\Database\Base_Builder;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Database\Raw_Sql;
/**
 * Builder for OCI8
 */
class Builder extends Base_Builder
{
    /**
     * Identifier escape character
     *
     * @var string
     */
    protected $escape_char = '"';
    /**
     * ORDER BY random keyword
     *
     * @var array
     */
    protected $random_keyword = ['"DBMS_RANDOM"."RANDOM"'];
    /**
     * COUNT string
     *
     * @used-by CI_DB_driver::count_all()
     * @used-by BaseBuilder::count_all_results()
     *
     * @var string
     */
    protected $count_string = 'SELECT COUNT(1) ';
    /**
     * A reference to the database connection.
     *
     * @var Connection
     */
    protected $db;
    /**
     * Generates a platform-specific insert string from the supplied data.
     */
    protected function _insert_batch(string $table, array $keys, array $values): string
    {
        $sql = $this->qb_options['sql'] ?? '';
        // if this is the first iteration of batch then we need to build skeleton sql
        if ($sql === '') {
            $insert_keys = implode(', ', $keys);
            $has_primary_key = in_array('PRIMARY', array_column($this->db->get_index_data($table), 'type'), true);
            // ORA-00001 measures
            $sql = 'INSERT' . ($has_primary_key ? '' : ' ALL') . ' INTO ' . $table . ' (' . $insert_keys . ")\n{:_table_:}";
            $this->qb_options['sql'] = $sql;
        }
        if (isset($this->qb_options['setQueryAsData'])) {
            $data = $this->qb_options['setQueryAsData'];
        } else {
            $data = implode(" FROM DUAL UNION ALL\n", array_map(static fn($value): string => 'SELECT ' . implode(', ', array_map(static fn($key, $index): string => $index . ' ' . $key, $keys, $value)), $values)) . " FROM DUAL\n";
        }
        return str_replace('{:_table_:}', $data, $sql);
    }
    /**
     * Generates a platform-specific replace string from the supplied data
     */
    protected function _replace(string $table, array $keys, array $values): string
    {
        $field_names = array_map(static fn($column_name): string => trim($column_name, '"'), $keys);
        $unique_indexes = array_filter($this->db->get_index_data($table), static function ($index) use ($field_names): bool {
            $has_all_fields = count(array_intersect($index->fields, $field_names)) === count($index->fields);
            return $index->type === 'PRIMARY' && $has_all_fields;
        });
        $replaceable_fields = array_filter($keys, static function ($column_name) use ($unique_indexes): bool {
            foreach ($unique_indexes as $index) {
                if (in_array(trim($column_name, '"'), $index->fields, true)) {
                    return false;
                }
            }
            return true;
        });
        $sql = 'MERGE INTO ' . $table . "\n USING (SELECT ";
        $sql .= implode(', ', array_map(static fn($column_name, $value): string => $value . ' ' . $column_name, $keys, $values));
        $sql .= ' FROM DUAL) "_replace" ON ( ';
        $on_list = [];
        $on_list[] = '1 != 1';
        foreach ($unique_indexes as $index) {
            $on_list[] = '(' . implode(' AND ', array_map(static fn($column_name): string => $table . '."' . $column_name . '" = "_replace"."' . $column_name . '"', $index->fields)) . ')';
        }
        $sql .= implode(' OR ', $on_list) . ') WHEN MATCHED THEN UPDATE SET ';
        $sql .= implode(', ', array_map(static fn($column_name): string => $column_name . ' = "_replace".' . $column_name, $replaceable_fields));
        $sql .= ' WHEN NOT MATCHED THEN INSERT (' . implode(', ', $replaceable_fields) . ') VALUES ';
        return $sql . (' (' . implode(', ', array_map(static fn($column_name): string => '"_replace".' . $column_name, $replaceable_fields)) . ')');
    }
    /**
     * Generates a platform-specific truncate string from the supplied data
     *
     * If the database does not support the truncate() command,
     * then this method maps to 'DELETE FROM table'
     */
    protected function _truncate(string $table): string
    {
        return 'TRUNCATE TABLE ' . $table;
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
        if ($limit !== null && $limit !== 0) {
            $this->qb_limit = $limit;
        }
        return parent::delete($where, null, $reset_data);
    }
    /**
     * Generates a platform-specific delete string from the supplied data
     */
    protected function _delete(string $table): string
    {
        if ($this->qb_limit) {
            $this->where('rownum <= ', $this->qb_limit, false);
            $this->qb_limit = false;
        }
        return parent::_delete($table);
    }
    /**
     * Generates a platform-specific update string from the supplied data
     */
    protected function _update(string $table, array $values): string
    {
        $val_str = [];
        foreach ($values as $key => $val) {
            $val_str[] = $key . ' = ' . $val;
        }
        if ($this->qb_limit) {
            $this->where('rownum <= ', $this->qb_limit, false);
        }
        return 'UPDATE ' . $this->compile_ignore('update') . $table . ' SET ' . implode(', ', $val_str) . $this->compile_where_having('QBWhere') . $this->compile_order_by();
    }
    /**
     * Generates a platform-specific LIMIT clause.
     */
    protected function _limit(string $sql, bool $offset_ignore = false): string
    {
        $offset = (int) ($offset_ignore === false ? $this->qb_offset : 0);
        // OFFSET-FETCH can be used only with the ORDER BY clause
        if (empty($this->qb_order_by)) {
            $sql .= ' ORDER BY 1';
        }
        return $sql . ' OFFSET ' . $offset . ' ROWS FETCH NEXT ' . $this->qb_limit . ' ROWS ONLY';
    }
    /**
     * Generates a platform-specific batch update string from the supplied data
     */
    protected function _update_batch(string $table, array $keys, array $values): string
    {
        $sql = $this->qb_options['sql'] ?? '';
        // if this is the first iteration of batch then we need to build skeleton sql
        if ($sql === '') {
            $constraints = $this->qb_options['constraints'] ?? [];
            if ($constraints === []) {
                if ($this->db->db_debug) {
                    throw new Database_Exception('You must specify a constraint to match on for batch updates.');
                }
                return '';
                // @codeCoverageIgnore
            }
            $update_fields = $this->qb_options['updateFields'] ?? $this->update_fields($keys, false, $constraints)->qb_options['updateFields'] ?? [];
            $alias = $this->qb_options['alias'] ?? '"_u"';
            // Oracle doesn't support ignore on updates so we will use MERGE
            $sql = 'MERGE INTO ' . $table . "\n";
            $sql .= "USING (\n{:_table_:}";
            $sql .= ') ' . $alias . "\n";
            $sql .= 'ON (' . implode(' AND ', array_map(static fn($key, $value) => $value instanceof Raw_Sql && is_string($key) ? $table . '.' . $key . ' = ' . $value : ($value instanceof Raw_Sql ? $value : $table . '.' . $value . ' = ' . $alias . '.' . $value), array_keys($constraints), $constraints)) . ")\n";
            $sql .= "WHEN MATCHED THEN UPDATE\n";
            $sql .= "SET\n";
            $sql .= implode(",\n", array_map(static fn($key, $value): string => $table . '.' . $key . ($value instanceof Raw_Sql ? ' = ' . $value : ' = ' . $alias . '.' . $value), array_keys($update_fields), $update_fields));
            $this->qb_options['sql'] = $sql;
        }
        if (isset($this->qb_options['setQueryAsData'])) {
            $data = $this->qb_options['setQueryAsData'];
        } else {
            $data = implode(" UNION ALL\n", array_map(static fn($value): string => 'SELECT ' . implode(', ', array_map(static fn($key, $index): string => $index . ' ' . $key, $keys, $value)) . ' FROM DUAL', $values)) . "\n";
        }
        return str_replace('{:_table_:}', $data, $sql);
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
            $constraints = $this->qb_options['constraints'] ?? [];
            if (empty($constraints)) {
                $field_names = array_map(static fn($column_name): string => trim($column_name, '"'), $keys);
                $unique_indexes = array_filter($this->db->get_index_data($table), static function ($index) use ($field_names): bool {
                    $has_all_fields = count(array_intersect($index->fields, $field_names)) === count($index->fields);
                    return ($index->type === 'PRIMARY' || $index->type === 'UNIQUE') && $has_all_fields;
                });
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
            $sql = 'MERGE INTO ' . $table . "\nUSING (\n{:_table_:}";
            $sql .= ") {$alias}\nON (";
            $sql .= implode(' AND ', array_map(static fn($key, $value) => $value instanceof Raw_Sql && is_string($key) ? $table . '.' . $key . ' = ' . $value : ($value instanceof Raw_Sql ? $value : $table . '.' . $value . ' = ' . $alias . '.' . $value), array_keys($constraints), $constraints)) . ")\n";
            $sql .= "WHEN MATCHED THEN UPDATE SET\n";
            $sql .= implode(",\n", array_map(static fn($key, $value): string => $key . ($value instanceof Raw_Sql ? " = {$value}" : " = {$alias}.{$value}"), array_keys($update_fields), $update_fields));
            $sql .= "\nWHEN NOT MATCHED THEN INSERT (" . implode(', ', $keys) . ")\nVALUES ";
            $sql .= ' (' . implode(', ', array_map(static fn($column_name): string => "{$alias}.{$column_name}", $keys)) . ')';
            $this->qb_options['sql'] = $sql;
        }
        if (isset($this->qb_options['setQueryAsData'])) {
            $data = $this->qb_options['setQueryAsData'];
        } else {
            $data = implode(" FROM DUAL UNION ALL\n", array_map(static fn($value): string => 'SELECT ' . implode(', ', array_map(static fn($key, $index): string => $index . ' ' . $key, $keys, $value)), $values)) . " FROM DUAL\n";
        }
        return str_replace('{:_table_:}', $data, $sql);
    }
    /**
     * Generates a platform-specific batch update string from the supplied data
     */
    protected function _delete_batch(string $table, array $keys, array $values): string
    {
        $sql = $this->qb_options['sql'] ?? '';
        // if this is the first iteration of batch then we need to build skeleton sql
        if ($sql === '') {
            $constraints = $this->qb_options['constraints'] ?? [];
            if ($constraints === []) {
                if ($this->db->db_debug) {
                    throw new Database_Exception('You must specify a constraint to match on for batch deletes.');
                    // @codeCoverageIgnore
                }
                return '';
                // @codeCoverageIgnore
            }
            $alias = $this->qb_options['alias'] ?? '_u';
            $sql = 'DELETE ' . $table . "\n";
            $sql .= "WHERE EXISTS (SELECT * FROM (\n{:_table_:}";
            $sql .= ') ' . $alias . "\n";
            $sql .= 'WHERE ' . implode(' AND ', array_map(static fn($key, $value) => $value instanceof Raw_Sql ? $value : (is_string($key) ? $table . '.' . $key . ' = ' . $alias . '.' . $value : $table . '.' . $value . ' = ' . $alias . '.' . $value), array_keys($constraints), $constraints));
            // convert binds in where
            foreach ($this->qb_where as $key => $where) {
                foreach ($this->binds as $field => $bind) {
                    $this->qb_where[$key]['condition'] = str_replace(':' . $field . ':', $bind[0], $where['condition']);
                }
            }
            $sql .= ' ' . str_replace('WHERE ', 'AND ', $this->compile_where_having('QBWhere')) . ')';
            $this->qb_options['sql'] = $sql;
        }
        if (isset($this->qb_options['setQueryAsData'])) {
            $data = $this->qb_options['setQueryAsData'];
        } else {
            $data = implode(" FROM DUAL UNION ALL\n", array_map(static fn($value): string => 'SELECT ' . implode(', ', array_map(static fn($key, $index): string => $index . ' ' . $key, $keys, $value)), $values)) . " FROM DUAL\n";
        }
        return str_replace('{:_table_:}', $data, $sql);
    }
    /**
     * Gets column names from a select query
     */
    protected function fields_from_query(string $sql): array
    {
        return $this->db->query('SELECT * FROM (' . $sql . ') "_u_" WHERE ROWNUM = 1')->get_field_names();
    }
}