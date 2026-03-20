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

use Code_Igniter\Database\Base_Builder;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Database\Raw_Sql;
use Code_Igniter\Exceptions\InvalidArgumentException;
/**
 * Builder for SQLite3
 */
class Builder extends Base_Builder
{
    /**
     * Default installs of SQLite typically do not
     * support limiting delete clauses.
     *
     * @var bool
     */
    protected $can_limit_deletes = false;
    /**
     * Default installs of SQLite do no support
     * limiting update queries in combo with WHERE.
     *
     * @var bool
     */
    protected $can_limit_where_updates = false;
    /**
     * ORDER BY random keyword
     *
     * @var array
     */
    protected $random_keyword = ['RANDOM()'];
    /**
     * @var array<string, string>
     */
    protected $supported_ignore_statements = ['insert' => 'OR IGNORE'];
    /**
     * Replace statement
     *
     * Generates a platform-specific replace string from the supplied data
     */
    protected function _replace(string $table, array $keys, array $values): string
    {
        return 'INSERT OR ' . parent::_replace($table, $keys, $values);
    }
    /**
     * Generates a platform-specific truncate string from the supplied data
     *
     * If the database does not support the TRUNCATE statement,
     * then this method maps to 'DELETE FROM table'
     */
    protected function _truncate(string $table): string
    {
        return 'DELETE FROM ' . $table;
    }
    /**
     * Generates a platform-specific batch update string from the supplied data
     */
    protected function _update_batch(string $table, array $keys, array $values): string
    {
        if (version_compare($this->db->get_version(), '3.33.0') >= 0) {
            return parent::_update_batch($table, $keys, $values);
        }
        $constraints = $this->qb_options['constraints'] ?? [];
        if ($constraints === []) {
            if ($this->db->db_debug) {
                throw new Database_Exception('You must specify a constraint to match on for batch updates.');
            }
            return '';
            // @codeCoverageIgnore
        }
        if (count($constraints) > 1 || isset($this->qb_options['setQueryAsData']) || current($constraints) instanceof Raw_Sql) {
            throw new Database_Exception('You are trying to use a feature which requires SQLite version 3.33 or higher.');
        }
        $index = current($constraints);
        $ids = [];
        $final = [];
        foreach ($values as $val) {
            $val = array_combine($keys, $val);
            $ids[] = $val[$index];
            foreach (array_keys($val) as $field) {
                if ($field !== $index) {
                    $final[$field][] = 'WHEN ' . $index . ' = ' . $val[$index] . ' THEN ' . $val[$field];
                }
            }
        }
        $cases = '';
        foreach ($final as $k => $v) {
            $cases .= $k . " = CASE \n" . implode("\n", $v) . "\n" . 'ELSE ' . $k . ' END, ';
        }
        $this->where($index . ' IN(' . implode(',', $ids) . ')', null, false);
        return 'UPDATE ' . $this->compile_ignore('update') . $table . ' SET ' . substr($cases, 0, -2) . $this->compile_where_having('QBWhere');
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
                $field_names = array_map(static fn($column_name): string => trim($column_name, '`'), $keys);
                $all_indexes = array_filter($this->db->get_index_data($table), static function ($index) use ($field_names): bool {
                    $has_all_fields = count(array_intersect($index->fields, $field_names)) === count($index->fields);
                    return ($index->type === 'PRIMARY' || $index->type === 'UNIQUE') && $has_all_fields;
                });
                foreach ($all_indexes as $index) {
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
            $alias = $this->qb_options['alias'] ?? '`excluded`';
            if (strtolower($alias) !== '`excluded`') {
                throw new InvalidArgumentException('SQLite alias is always named "excluded". A custom alias cannot be used.');
            }
            $update_fields = $this->qb_options['updateFields'] ?? $this->update_fields($keys, false, $constraints)->qb_options['updateFields'] ?? [];
            $sql = 'INSERT INTO ' . $table . ' (';
            $sql .= implode(', ', array_map(static fn($column_name): string => $column_name, $keys));
            $sql .= ")\n";
            $sql .= '{:_table_:}';
            $sql .= 'ON CONFLICT(' . implode(',', $constraints) . ")\n";
            $sql .= "DO UPDATE SET\n";
            $sql .= implode(",\n", array_map(static fn($key, $value): string => $key . ($value instanceof Raw_Sql ? " = {$value}" : " = {$alias}.{$value}"), array_keys($update_fields), $update_fields));
            $this->qb_options['sql'] = $sql;
        }
        if (isset($this->qb_options['setQueryAsData'])) {
            $has_where = stripos($this->qb_options['setQueryAsData'], 'WHERE') > 0;
            $data = $this->qb_options['setQueryAsData'] . ($has_where ? '' : "\nWHERE 1 = 1\n");
        } else {
            $data = 'VALUES ' . implode(', ', $this->format_values($values)) . "\n";
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
            $sql = 'DELETE FROM ' . $table . "\n";
            if (current($constraints) instanceof Raw_Sql && $this->db->db_debug) {
                throw new Database_Exception('You cannot use RawSql for constraint in SQLite.');
                // @codeCoverageIgnore
            }
            if (is_string(current(array_keys($constraints)))) {
                $concat1 = implode(' || ', array_keys($constraints));
                $concat2 = implode(' || ', array_values($constraints));
            } else {
                $concat1 = implode(' || ', $constraints);
                $concat2 = $concat1;
            }
            $sql .= "WHERE {$concat1} IN (SELECT {$concat2} FROM (\n{:_table_:}))";
            // where is not supported
            if ($this->qb_where !== [] && $this->db->db_debug) {
                throw new Database_Exception('You cannot use WHERE with SQLite.');
                // @codeCoverageIgnore
            }
            $this->qb_options['sql'] = $sql;
        }
        if (isset($this->qb_options['setQueryAsData'])) {
            $data = $this->qb_options['setQueryAsData'];
        } else {
            $data = implode(" UNION ALL\n", array_map(static fn($value): string => 'SELECT ' . implode(', ', array_map(static fn($key, $index): string => $index . ' ' . $key, $keys, $value)), $values)) . "\n";
        }
        return str_replace('{:_table_:}', $data, $sql);
    }
}