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

use Code_Igniter\Database\Forge as BaseForge;
/**
 * Forge for OCI8
 */
class Forge extends Base_Forge
{
    /**
     * DROP INDEX statement
     *
     * @var string
     */
    protected $drop_index_str = 'DROP INDEX %s';
    /**
     * CREATE DATABASE statement
     *
     * @var false
     */
    protected $create_database_str = false;
    /**
     * CREATE TABLE IF statement
     *
     * @var false
     *
     * @deprecated This is no longer used.
     */
    protected $create_table_if_str = false;
    /**
     * DROP TABLE IF EXISTS statement
     *
     * @var false
     */
    protected $drop_table_if_str = false;
    /**
     * DROP DATABASE statement
     *
     * @var false
     */
    protected $drop_database_str = false;
    /**
     * UNSIGNED support
     *
     * @var array|bool
     */
    protected $unsigned = false;
    /**
     * NULL value representation in CREATE/ALTER TABLE statements
     *
     * @var string
     */
    protected $null = 'NULL';
    /**
     * RENAME TABLE statement
     *
     * @var string
     */
    protected $rename_table_str = 'ALTER TABLE %s RENAME TO %s';
    /**
     * DROP CONSTRAINT statement
     *
     * @var string
     */
    protected $drop_constraint_str = 'ALTER TABLE %s DROP CONSTRAINT %s';
    /**
     * Foreign Key Allowed Actions
     *
     * @var array
     */
    protected $fk_allow_actions = ['CASCADE', 'SET NULL', 'NO ACTION'];
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
        $sql = 'ALTER TABLE ' . $this->db->escape_identifiers($table);
        if ($alter_type === 'DROP') {
            $column_names_to_drop = $processed_fields;
            $fields = array_map(fn($field) => $this->db->escape_identifiers(trim($field)), is_string($column_names_to_drop) ? explode(',', $column_names_to_drop) : $column_names_to_drop);
            return $sql . ' DROP (' . implode(',', $fields) . ') CASCADE CONSTRAINT INVALIDATE';
        }
        if ($alter_type === 'CHANGE') {
            $alter_type = 'MODIFY';
        }
        $nullable_map = array_column($this->db->get_field_data($table), 'nullable', 'name');
        $sqls = [];
        for ($i = 0, $c = count($processed_fields); $i < $c; $i++) {
            if ($alter_type === 'MODIFY') {
                // If a null constraint is added to a column with a null constraint,
                // ORA-01451 will occur,
                // so add null constraint is used only when it is different from the current null constraint.
                // If a not null constraint is added to a column with a not null constraint,
                // ORA-01442 will occur.
                $want_to_add_null = !str_contains($processed_fields[$i]['null'], ' NOT');
                $current_nullable = $nullable_map[$processed_fields[$i]['name']];
                if ($want_to_add_null && $current_nullable === true) {
                    $processed_fields[$i]['null'] = '';
                } elseif ($processed_fields[$i]['null'] === '' && $current_nullable === false) {
                    // Nullable by default
                    $processed_fields[$i]['null'] = ' NULL';
                } elseif ($want_to_add_null === false && $current_nullable === false) {
                    $processed_fields[$i]['null'] = '';
                }
            }
            if ($processed_fields[$i]['_literal'] !== false) {
                $processed_fields[$i] = "\n\t" . $processed_fields[$i]['_literal'];
            } else {
                $processed_fields[$i]['_literal'] = "\n\t" . $this->_process_column($processed_fields[$i]);
                if (!empty($processed_fields[$i]['comment'])) {
                    $sqls[] = 'COMMENT ON COLUMN ' . $this->db->escape_identifiers($table) . '.' . $this->db->escape_identifiers($processed_fields[$i]['name']) . ' IS ' . $processed_fields[$i]['comment'];
                }
                if ($alter_type === 'MODIFY' && !empty($processed_fields[$i]['new_name'])) {
                    $sqls[] = $sql . ' RENAME COLUMN ' . $this->db->escape_identifiers($processed_fields[$i]['name']) . ' TO ' . $this->db->escape_identifiers($processed_fields[$i]['new_name']);
                }
                $processed_fields[$i] = "\n\t" . $processed_fields[$i]['_literal'];
            }
        }
        $sql .= ' ' . $alter_type . ' ';
        $sql .= count($processed_fields) === 1 ? $processed_fields[0] : '(' . implode(',', $processed_fields) . ')';
        // RENAME COLUMN must be executed after MODIFY
        array_unshift($sqls, $sql);
        return $sqls;
    }
    /**
     * Field attribute AUTO_INCREMENT
     *
     * @return void
     */
    protected function _attribute_auto_increment(array &$attributes, array &$field)
    {
        if (!empty($attributes['AUTO_INCREMENT']) && $attributes['AUTO_INCREMENT'] === true && str_contains(strtolower($field['type']), 'number') && version_compare($this->db->get_version(), '12.1', '>=')) {
            $field['auto_increment'] = ' GENERATED BY DEFAULT ON NULL AS IDENTITY';
        }
    }
    /**
     * Process column
     */
    protected function _process_column(array $processed_field): string
    {
        $constraint = '';
        // @todo: can't cover multi pattern when set type.
        if ($processed_field['type'] === 'VARCHAR2' && str_starts_with($processed_field['length'], "('")) {
            $constraint = ' CHECK(' . $this->db->escape_identifiers($processed_field['name']) . ' IN ' . $processed_field['length'] . ')';
            $processed_field['length'] = '(' . max(array_map(mb_strlen(...), explode("','", mb_substr($processed_field['length'], 2, -2)))) . ')' . $constraint;
        } elseif (isset($this->primary_keys['fields']) && count($this->primary_keys['fields']) === 1 && $processed_field['name'] === $this->primary_keys['fields'][0]) {
            $processed_field['unique'] = '';
        }
        return $this->db->escape_identifiers($processed_field['name']) . ' ' . $processed_field['type'] . $processed_field['length'] . $processed_field['unsigned'] . $processed_field['default'] . $processed_field['auto_increment'] . $processed_field['null'] . $processed_field['unique'];
    }
    /**
     * Performs a data type mapping between different databases.
     *
     * @return void
     */
    protected function _attribute_type(array &$attributes)
    {
        // Reset field lengths for data types that don't support it
        // Usually overridden by drivers
        switch (strtoupper($attributes['TYPE'])) {
            case 'TINYINT':
                $attributes['CONSTRAINT'] ??= 3;
            // no break
            case 'SMALLINT':
                $attributes['CONSTRAINT'] ??= 5;
            // no break
            case 'MEDIUMINT':
                $attributes['CONSTRAINT'] ??= 7;
            // no break
            case 'INT':
            case 'INTEGER':
                $attributes['CONSTRAINT'] ??= 11;
            // no break
            case 'BIGINT':
                $attributes['CONSTRAINT'] ??= 19;
            // no break
            case 'NUMERIC':
                $attributes['TYPE'] = 'NUMBER';
                return;
            case 'BOOLEAN':
                $attributes['TYPE'] = 'NUMBER';
                $attributes['CONSTRAINT'] = 1;
                $attributes['UNSIGNED'] = true;
                return;
            case 'DOUBLE':
                $attributes['TYPE'] = 'FLOAT';
                $attributes['CONSTRAINT'] ??= 126;
                return;
            case 'DATETIME':
            case 'TIME':
                $attributes['TYPE'] = 'DATE';
                return;
            case 'SET':
            case 'ENUM':
            case 'VARCHAR':
                $attributes['CONSTRAINT'] ??= 255;
            // no break
            case 'TEXT':
            case 'MEDIUMTEXT':
                $attributes['CONSTRAINT'] ??= 4000;
                $attributes['TYPE'] = 'VARCHAR2';
        }
    }
    /**
     * Generates a platform-specific DROP TABLE string
     *
     * @return bool|string
     */
    protected function _drop_table(string $table, bool $if_exists, bool $cascade)
    {
        $sql = parent::_drop_table($table, $if_exists, $cascade);
        if ($sql !== true && $cascade) {
            $sql .= ' CASCADE CONSTRAINTS PURGE';
        } elseif ($sql !== true) {
            $sql .= ' PURGE';
        }
        return $sql;
    }
    /**
     * Constructs sql to check if key is a constraint.
     */
    protected function _drop_key_as_constraint(string $table, string $constraint_name): string
    {
        return "SELECT constraint_name FROM all_constraints WHERE table_name = '" . trim($table, '"') . "' AND index_name = '" . trim($constraint_name, '"') . "'";
    }
}