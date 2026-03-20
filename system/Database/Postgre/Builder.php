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

use Code_Igniter\Database\Base_Builder;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Database\Raw_Sql;
use Code_Igniter\Exceptions\InvalidArgumentException;
/**
 * Builder for Postgre
 */
class Builder extends Base_Builder
{
    /**
     * ORDER BY random keyword
     *
     * @var array
     */
    protected $random_keyword = ['RANDOM()'];
    /**
     * Specifies which sql statements
     * support the ignore option.
     *
     * @var array<string, string>
     */
    protected $supported_ignore_statements = ['insert' => 'ON CONFLICT DO NOTHING'];
    /**
     * Checks if the ignore option is supported by
     * the Database Driver for the specific statement.
     *
     * @return string
     */
    protected function compile_ignore(string $statement)
    {
        $sql = parent::compile_ignore($statement);
        if (!empty($sql)) {
            $sql = ' ' . trim($sql);
        }
        return $sql;
    }
    /**
     * ORDER BY
     *
     * @param string $direction ASC, DESC or RANDOM
     *
     * @return BaseBuilder
     */
    public function order_by(string $order_by, string $direction = '', ?bool $escape = null)
    {
        $direction = strtoupper(trim($direction));
        if ($direction === 'RANDOM') {
            if (ctype_digit($order_by)) {
                $order_by = (float) ($order_by > 1 ? "0.{$order_by}" : $order_by);
            }
            if (is_float($order_by)) {
                $this->db->simple_query("SET SEED {$order_by}");
            }
            $order_by = $this->random_keyword[0];
            $direction = '';
            $escape = false;
        }
        return parent::order_by($order_by, $direction, $escape);
    }
    /**
     * Increments a numeric column by the specified value.
     *
     * @return mixed
     *
     * @throws DatabaseException
     */
    public function increment(string $column, int $value = 1)
    {
        $column = $this->db->protect_identifiers($column);
        $sql = $this->_update($this->qb_from[0], [$column => "to_number({$column}, '9999999') + {$value}"]);
        if (!$this->test_mode) {
            $this->reset_write();
            return $this->db->query($sql, $this->binds, false);
        }
        return true;
    }
    /**
     * Decrements a numeric column by the specified value.
     *
     * @return mixed
     *
     * @throws DatabaseException
     */
    public function decrement(string $column, int $value = 1)
    {
        $column = $this->db->protect_identifiers($column);
        $sql = $this->_update($this->qb_from[0], [$column => "to_number({$column}, '9999999') - {$value}"]);
        if (!$this->test_mode) {
            $this->reset_write();
            return $this->db->query($sql, $this->binds, false);
        }
        return true;
    }
    /**
     * Compiles an replace into string and runs the query.
     * Because PostgreSQL doesn't support the replace into command,
     * we simply do a DELETE and an INSERT on the first key/value
     * combo, assuming that it's either the primary key or a unique key.
     *
     * @param array|null $set An associative array of insert values
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
        $set = $this->binds;
        array_walk($set, static function (array &$item): void {
            $item = $item[0];
        });
        $key = array_key_first($set);
        $value = $set[$key];
        $builder = $this->db->table($table);
        $exists = $builder->where($key, $value, true)->get()->get_first_row();
        if (empty($exists) && $this->test_mode) {
            $result = $this->get_compiled_insert();
        } elseif (empty($exists)) {
            $result = $builder->insert($set);
        } elseif ($this->test_mode) {
            $result = $this->where($key, $value, true)->get_compiled_update();
        } else {
            array_shift($set);
            $result = $builder->where($key, $value, true)->update($set);
        }
        unset($builder);
        $this->reset_write();
        $this->binds = [];
        return $result;
    }
    /**
     * Generates a platform-specific insert string from the supplied data
     */
    protected function _insert(string $table, array $keys, array $unescaped_keys): string
    {
        return trim(sprintf('INSERT INTO %s (%s) VALUES (%s) %s', $table, implode(', ', $keys), implode(', ', $unescaped_keys), $this->compile_ignore('insert')));
    }
    /**
     * Generates a platform-specific insert string from the supplied data.
     */
    protected function _insert_batch(string $table, array $keys, array $values): string
    {
        $sql = $this->qb_options['sql'] ?? '';
        // if this is the first iteration of batch then we need to build skeleton sql
        if ($sql === '') {
            $sql = 'INSERT INTO ' . $table . '(' . implode(', ', $keys) . ")\n{:_table_:}\n";
            $sql .= $this->compile_ignore('insert');
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
        if ($limit !== null && $limit !== 0 || !empty($this->qb_limit)) {
            throw new Database_Exception('PostgreSQL does not allow LIMITs on DELETE queries.');
        }
        return parent::delete($where, $limit, $reset_data);
    }
    /**
     * Generates a platform-specific LIMIT clause.
     */
    protected function _limit(string $sql, bool $offset_ignore = false): string
    {
        return $sql . ' LIMIT ' . $this->qb_limit . ($this->qb_offset ? " OFFSET {$this->qb_offset}" : '');
    }
    /**
     * Generates a platform-specific update string from the supplied data
     *
     * @throws DatabaseException
     */
    protected function _update(string $table, array $values): string
    {
        if (!empty($this->qb_limit)) {
            throw new Database_Exception('Postgres does not support LIMITs with UPDATE queries.');
        }
        $this->qb_order_by = [];
        return parent::_update($table, $values);
    }
    /**
     * Generates a platform-specific delete string from the supplied data
     */
    protected function _delete(string $table): string
    {
        $this->qb_limit = false;
        return parent::_delete($table);
    }
    /**
     * Generates a platform-specific truncate string from the supplied data
     *
     * If the database does not support the truncate() command,
     * then this method maps to 'DELETE FROM table'
     */
    protected function _truncate(string $table): string
    {
        return 'TRUNCATE ' . $table . ' RESTART IDENTITY';
    }
    /**
     * Platform independent LIKE statement builder.
     *
     * In PostgreSQL, the ILIKE operator will perform case insensitive
     * searches according to the current locale.
     *
     * @see https://www.postgresql.org/docs/9.2/static/functions-matching.html
     */
    protected function _like_statement(?string $prefix, string $column, ?string $not, string $bind, bool $insensitive_search = false): string
    {
        $op = $insensitive_search ? 'ILIKE' : 'LIKE';
        return "{$prefix} {$column} {$not} {$op} :{$bind}:";
    }
    /**
     * Generates the JOIN portion of the query
     *
     * @param RawSql|string $cond
     *
     * @return BaseBuilder
     */
    public function join(string $table, $cond, string $type = '', ?bool $escape = null)
    {
        if (!in_array('FULL OUTER', $this->join_types, true)) {
            $this->join_types = array_merge($this->join_types, ['FULL OUTER']);
        }
        return parent::join($table, $cond, $type, $escape);
    }
    /**
     * Generates a platform-specific batch update string from the supplied data
     *
     * @used-by batchExecute()
     *
     * @param string                 $table  Protected table name
     * @param list<string>           $keys   QBKeys
     * @param list<list<int|string>> $values QBSet
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
                    // @codeCoverageIgnore
                }
                return '';
                // @codeCoverageIgnore
            }
            $update_fields = $this->qb_options['updateFields'] ?? $this->update_fields($keys, false, $constraints)->qb_options['updateFields'] ?? [];
            $alias = $this->qb_options['alias'] ?? '_u';
            $sql = 'UPDATE ' . $this->compile_ignore('update') . $table . "\n";
            $sql .= "SET\n";
            $that = $this;
            $sql .= implode(",\n", array_map(static fn($key, $value): string => $key . ($value instanceof Raw_Sql ? ' = ' . $value : ' = ' . $that->cast($alias . '.' . $value, $that->get_field_type($table, $key))), array_keys($update_fields), $update_fields)) . "\n";
            $sql .= "FROM (\n{:_table_:}";
            $sql .= ') ' . $alias . "\n";
            $sql .= 'WHERE ' . implode(' AND ', array_map(static function ($key, $value) use ($table, $alias, $that): string|Raw_Sql {
                if ($value instanceof Raw_Sql && is_string($key)) {
                    return $table . '.' . $key . ' = ' . $value;
                }
                if ($value instanceof Raw_Sql) {
                    return $value;
                }
                return $table . '.' . $value . ' = ' . $that->cast($alias . '.' . $value, $that->get_field_type($table, $value));
            }, array_keys($constraints), $constraints));
            $this->qb_options['sql'] = $sql;
        }
        if (isset($this->qb_options['setQueryAsData'])) {
            $data = $this->qb_options['setQueryAsData'];
        } else {
            $data = implode(" UNION ALL\n", array_map(static fn($value): string => 'SELECT ' . implode(', ', array_map(static fn($key, $index): string => $index . ' ' . $key, $keys, $value)), $values)) . "\n";
        }
        return str_replace('{:_table_:}', $data, $sql);
    }
    /**
     * Returns cast expression.
     *
     * @TODO move this to BaseBuilder in 4.5.0
     */
    private function cast(string $expression, ?string $type): string
    {
        return $type === null ? $expression : 'CAST(' . $expression . ' AS ' . strtoupper($type) . ')';
    }
    /**
     * Returns the filed type from database meta data.
     *
     * @param string $table     Protected table name.
     * @param string $fieldName Field name. May be protected.
     */
    private function get_field_type(string $table, string $field_name): ?string
    {
        $field_name = trim($field_name, $this->db->escape_char);
        if (!isset($this->qb_options['fieldTypes'][$table])) {
            $this->qb_options['fieldTypes'][$table] = [];
            foreach ($this->db->get_field_data($table) as $field) {
                $type = $field->type;
                // If `character` (or `char`) lacks a specifier, it is equivalent
                // to `character(1)`.
                // See https://www.postgresql.org/docs/current/datatype-character.html
                if ($field->type === 'character') {
                    $type = $field->type . '(' . $field->max_length . ')';
                }
                $this->qb_options['fieldTypes'][$table][$field->name] = $type;
            }
        }
        return $this->qb_options['fieldTypes'][$table][$field_name] ?? null;
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
            $field_names = array_map(static fn($column_name): string => trim($column_name, '"'), $keys);
            $constraints = $this->qb_options['constraints'] ?? [];
            if (empty($constraints)) {
                $all_indexes = array_filter($this->db->get_index_data($table), static function ($index) use ($field_names): bool {
                    $has_all_fields = count(array_intersect($index->fields, $field_names)) === count($index->fields);
                    return ($index->type === 'UNIQUE' || $index->type === 'PRIMARY') && $has_all_fields;
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
            // in value set - replace null with DEFAULT where constraint is presumed not null
            // autoincrement identity field must use DEFAULT and not NULL
            // this could be removed in favour of leaving to developer but does make things easier and function like other DBMS
            foreach ($constraints as $constraint) {
                $key = array_search(trim((string) $constraint, '"'), $field_names, true);
                if ($key !== false) {
                    foreach ($values as $array_key => $value) {
                        if (strtoupper((string) $value[$key]) === 'NULL') {
                            $values[$array_key][$key] = 'DEFAULT';
                        }
                    }
                }
            }
            $alias = $this->qb_options['alias'] ?? '"excluded"';
            if (strtolower($alias) !== '"excluded"') {
                throw new InvalidArgumentException('Postgres alias is always named "excluded". A custom alias cannot be used.');
            }
            $update_fields = $this->qb_options['updateFields'] ?? $this->update_fields($keys, false, $constraints)->qb_options['updateFields'] ?? [];
            $sql = 'INSERT INTO ' . $table . ' (';
            $sql .= implode(', ', $keys);
            $sql .= ")\n";
            $sql .= '{:_table_:}';
            $sql .= 'ON CONFLICT(' . implode(',', $constraints) . ")\n";
            $sql .= "DO UPDATE SET\n";
            $sql .= implode(",\n", array_map(static fn($key, $value): string => $key . ($value instanceof Raw_Sql ? " = {$value}" : " = {$alias}.{$value}"), array_keys($update_fields), $update_fields));
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
            $sql = 'DELETE FROM ' . $table . "\n";
            $sql .= "USING (\n{:_table_:}";
            $sql .= ') ' . $alias . "\n";
            $that = $this;
            $sql .= 'WHERE ' . implode(' AND ', array_map(static function ($key, $value) use ($table, $alias, $that): Raw_Sql|string {
                if ($value instanceof Raw_Sql) {
                    return $value;
                }
                if (is_string($key)) {
                    return $table . '.' . $key . ' = ' . $that->cast($alias . '.' . $value, $that->get_field_type($table, $key));
                }
                return $table . '.' . $value . ' = ' . $alias . '.' . $value;
            }, array_keys($constraints), $constraints));
            // convert binds in where
            foreach ($this->qb_where as $key => $where) {
                foreach ($this->binds as $field => $bind) {
                    $this->qb_where[$key]['condition'] = str_replace(':' . $field . ':', $bind[0], $where['condition']);
                }
            }
            $sql .= ' ' . str_replace('WHERE ', 'AND ', $this->compile_where_having('QBWhere'));
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