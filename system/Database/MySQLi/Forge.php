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

use Code_Igniter\Database\Forge as BaseForge;
/**
 * Forge for MySQLi
 */
class Forge extends Base_Forge
{
    /**
     * CREATE DATABASE statement
     *
     * @var string
     */
    protected $create_database_str = 'CREATE DATABASE %s CHARACTER SET %s COLLATE %s';
    /**
     * CREATE DATABASE IF statement
     *
     * @var string
     */
    protected $create_database_if_str = 'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET %s COLLATE %s';
    /**
     * DROP CONSTRAINT statement
     *
     * @var string
     */
    protected $drop_constraint_str = 'ALTER TABLE %s DROP FOREIGN KEY %s';
    /**
     * CREATE TABLE keys flag
     *
     * Whether table keys are created from within the
     * CREATE TABLE statement.
     *
     * @var bool
     */
    protected $create_table_keys = true;
    /**
     * UNSIGNED support
     *
     * @var array
     */
    protected $_unsigned = ['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT', 'REAL', 'DOUBLE', 'DOUBLE PRECISION', 'FLOAT', 'DECIMAL', 'NUMERIC'];
    /**
     * Table Options list which required to be quoted
     *
     * @var array
     */
    protected $_quoted_table_options = ['COMMENT', 'COMPRESSION', 'CONNECTION', 'DATA DIRECTORY', 'INDEX DIRECTORY', 'ENCRYPTION', 'PASSWORD'];
    /**
     * NULL value representation in CREATE/ALTER TABLE statements
     *
     * @var string
     *
     * @internal
     */
    protected $null = 'NULL';
    /**
     * CREATE TABLE attributes
     *
     * @param array $attributes Associative array of table attributes
     */
    protected function _create_table_attributes(array $attributes): string
    {
        $sql = '';
        foreach (array_keys($attributes) as $key) {
            if (is_string($key)) {
                $sql .= ' ' . strtoupper($key) . ' = ';
                if (in_array(strtoupper($key), $this->_quoted_table_options, true)) {
                    $sql .= $this->db->escape($attributes[$key]);
                } else {
                    $sql .= $this->db->escape_string($attributes[$key]);
                }
            }
        }
        if ($this->db->charset !== '' && !str_contains($sql, 'CHARACTER SET') && !str_contains($sql, 'CHARSET')) {
            $sql .= ' DEFAULT CHARACTER SET = ' . $this->db->escape_string($this->db->charset);
        }
        if ($this->db->db_collat !== '' && !str_contains($sql, 'COLLATE')) {
            $sql .= ' COLLATE = ' . $this->db->escape_string($this->db->db_collat);
        }
        return $sql;
    }
    /**
     * ALTER TABLE
     *
     * @param string       $alterType       ALTER type
     * @param string       $table           Table name
     * @param array|string $processedFields Processed column definitions
     *                                      or column names to DROP
     *
     * @return ($alterType is 'DROP' ? string : list<string>)
     */
    protected function _alter_table(string $alter_type, string $table, $processed_fields)
    {
        if ($alter_type === 'DROP') {
            return parent::_alter_table($alter_type, $table, $processed_fields);
        }
        $sql = 'ALTER TABLE ' . $this->db->escape_identifiers($table);
        foreach ($processed_fields as $i => $field) {
            if ($field['_literal'] !== false) {
                $processed_fields[$i] = $alter_type === 'ADD' ? "\n\tADD " . $field['_literal'] : "\n\tMODIFY " . $field['_literal'];
            } else {
                if ($alter_type === 'ADD') {
                    $processed_fields[$i]['_literal'] = "\n\tADD ";
                } else {
                    $processed_fields[$i]['_literal'] = empty($field['new_name']) ? "\n\tMODIFY " : "\n\tCHANGE ";
                }
                $processed_fields[$i] = $processed_fields[$i]['_literal'] . $this->_process_column($processed_fields[$i]);
            }
        }
        return [$sql . implode(',', $processed_fields)];
    }
    /**
     * Process column
     */
    protected function _process_column(array $processed_field): string
    {
        $extra_clause = isset($processed_field['after']) ? ' AFTER ' . $this->db->escape_identifiers($processed_field['after']) : '';
        if (empty($extra_clause) && isset($processed_field['first']) && $processed_field['first'] === true) {
            $extra_clause = ' FIRST';
        }
        return $this->db->escape_identifiers($processed_field['name']) . (empty($processed_field['new_name']) ? '' : ' ' . $this->db->escape_identifiers($processed_field['new_name'])) . ' ' . $processed_field['type'] . $processed_field['length'] . $processed_field['unsigned'] . $processed_field['null'] . $processed_field['default'] . $processed_field['auto_increment'] . $processed_field['unique'] . (empty($processed_field['comment']) ? '' : ' COMMENT ' . $processed_field['comment']) . $extra_clause;
    }
    /**
     * Generates SQL to add indexes
     *
     * @param bool $asQuery When true returns stand alone SQL, else partial SQL used with CREATE TABLE
     */
    protected function _process_indexes(string $table, bool $as_query = false): array
    {
        $sqls = [''];
        $index = 0;
        for ($i = 0, $c = count($this->keys); $i < $c; $i++) {
            $index = $i;
            if ($as_query === false) {
                $index = 0;
            }
            if (isset($this->keys[$i]['fields'])) {
                for ($i2 = 0, $c2 = count($this->keys[$i]['fields']); $i2 < $c2; $i2++) {
                    if (!isset($this->fields[$this->keys[$i]['fields'][$i2]])) {
                        unset($this->keys[$i]['fields'][$i2]);
                        continue;
                    }
                }
            }
            if (!is_array($this->keys[$i]['fields'])) {
                $this->keys[$i]['fields'] = [$this->keys[$i]['fields']];
            }
            $unique = in_array($i, $this->unique_keys, true) ? 'UNIQUE ' : '';
            $key_name = $this->db->escape_identifiers($this->keys[$i]['keyName'] === '' ? implode('_', $this->keys[$i]['fields']) : $this->keys[$i]['keyName']);
            if ($as_query) {
                $sqls[$index] = 'ALTER TABLE ' . $this->db->escape_identifiers($table) . " ADD {$unique}KEY " . $key_name . ' (' . implode(', ', $this->db->escape_identifiers($this->keys[$i]['fields'])) . ')';
            } else {
                $sqls[$index] .= ",\n\t{$unique}KEY " . $key_name . ' (' . implode(', ', $this->db->escape_identifiers($this->keys[$i]['fields'])) . ')';
            }
        }
        $this->keys = [];
        return $sqls;
    }
    /**
     * Drop Key
     */
    public function drop_key(string $table, string $key_name, bool $prefix_key_name = true): bool
    {
        $sql = sprintf($this->drop_index_str, $this->db->escape_identifiers($key_name), $this->db->escape_identifiers($this->db->db_prefix . $table));
        return $this->db->query($sql);
    }
    /**
     * Drop Primary Key
     */
    public function drop_primary_key(string $table, string $key_name = ''): bool
    {
        $sql = sprintf('ALTER TABLE %s DROP PRIMARY KEY', $this->db->escape_identifiers($this->db->db_prefix . $table));
        return $this->db->query($sql);
    }
}