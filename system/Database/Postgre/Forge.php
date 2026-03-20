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

use Code_Igniter\Database\Forge as BaseForge;
/**
 * Forge for Postgre
 */
class Forge extends Base_Forge
{
    /**
     * CHECK DATABASE EXIST statement
     *
     * @var string
     */
    protected $check_database_exist_str = 'SELECT 1 FROM pg_database WHERE datname = ?';
    /**
     * DROP CONSTRAINT statement
     *
     * @var string
     */
    protected $drop_constraint_str = 'ALTER TABLE %s DROP CONSTRAINT %s';
    /**
     * DROP INDEX statement
     *
     * @var string
     */
    protected $drop_index_str = 'DROP INDEX %s';
    /**
     * UNSIGNED support
     *
     * @var array
     */
    protected $_unsigned = ['INT2' => 'INTEGER', 'SMALLINT' => 'INTEGER', 'INT' => 'BIGINT', 'INT4' => 'BIGINT', 'INTEGER' => 'BIGINT', 'INT8' => 'NUMERIC', 'BIGINT' => 'NUMERIC', 'REAL' => 'DOUBLE PRECISION', 'FLOAT' => 'DOUBLE PRECISION'];
    /**
     * NULL value representation in CREATE/ALTER TABLE statements
     *
     * @var string
     *
     * @internal
     */
    protected $null = 'NULL';
    /**
     * @var Connection
     */
    protected $db;
    /**
     * CREATE TABLE attributes
     *
     * @param array $attributes Associative array of table attributes
     */
    protected function _create_table_attributes(array $attributes): string
    {
        return '';
    }
    /**
     * @param array|string $processedFields Processed column definitions
     *                                      or column names to DROP
     *
     * @return ($alterType is 'DROP' ? string : false|list<string>)
     */
    protected function _alter_table(string $alter_type, string $table, $processed_fields)
    {
        if (in_array($alter_type, ['DROP', 'ADD'], true)) {
            return parent::_alter_table($alter_type, $table, $processed_fields);
        }
        $sql = 'ALTER TABLE ' . $this->db->escape_identifiers($table);
        $sqls = [];
        foreach ($processed_fields as $field) {
            if ($field['_literal'] !== false) {
                return false;
            }
            if (version_compare($this->db->get_version(), '8', '>=') && isset($field['type'])) {
                $sqls[] = $sql . ' ALTER COLUMN ' . $this->db->escape_identifiers($field['name']) . " TYPE {$field['type']}{$field['length']}";
            }
            if (!empty($field['default'])) {
                $sqls[] = $sql . ' ALTER COLUMN ' . $this->db->escape_identifiers($field['name']) . " SET {$field['default']}";
            }
            $nullable = true;
            // Nullable by default.
            if (isset($field['null']) && ($field['null'] === false || $field['null'] === ' NOT ' . $this->null)) {
                $nullable = false;
            }
            $sqls[] = $sql . ' ALTER COLUMN ' . $this->db->escape_identifiers($field['name']) . ($nullable ? ' DROP' : ' SET') . ' NOT NULL';
            if (!empty($field['new_name'])) {
                $sqls[] = $sql . ' RENAME COLUMN ' . $this->db->escape_identifiers($field['name']) . ' TO ' . $this->db->escape_identifiers($field['new_name']);
            }
            if (!empty($field['comment'])) {
                $sqls[] = 'COMMENT ON COLUMN' . $this->db->escape_identifiers($table) . '.' . $this->db->escape_identifiers($field['name']) . " IS {$field['comment']}";
            }
        }
        return $sqls;
    }
    /**
     * Process column
     */
    protected function _process_column(array $processed_field): string
    {
        return $this->db->escape_identifiers($processed_field['name']) . ' ' . $processed_field['type'] . ($processed_field['type'] === 'text' ? '' : $processed_field['length']) . $processed_field['default'] . $processed_field['null'] . $processed_field['auto_increment'] . $processed_field['unique'];
    }
    /**
     * Performs a data type mapping between different databases.
     */
    protected function _attribute_type(array &$attributes)
    {
        // Reset field lengths for data types that don't support it
        if (isset($attributes['CONSTRAINT']) && str_contains(strtolower($attributes['TYPE']), 'int')) {
            $attributes['CONSTRAINT'] = null;
        }
        switch (strtoupper($attributes['TYPE'])) {
            case 'TINYINT':
                $attributes['TYPE'] = 'SMALLINT';
                $attributes['UNSIGNED'] = false;
                break;
            case 'MEDIUMINT':
                $attributes['TYPE'] = 'INTEGER';
                $attributes['UNSIGNED'] = false;
                break;
            case 'DATETIME':
                $attributes['TYPE'] = 'TIMESTAMP';
                break;
            case 'BLOB':
                $attributes['TYPE'] = 'BYTEA';
                break;
            default:
                break;
        }
    }
    /**
     * Field attribute AUTO_INCREMENT
     */
    protected function _attribute_auto_increment(array &$attributes, array &$field)
    {
        if (!empty($attributes['AUTO_INCREMENT']) && $attributes['AUTO_INCREMENT'] === true) {
            $field['type'] = $field['type'] === 'NUMERIC' || $field['type'] === 'BIGINT' ? 'BIGSERIAL' : 'SERIAL';
        }
    }
    /**
     * Generates a platform-specific DROP TABLE string
     */
    protected function _drop_table(string $table, bool $if_exists, bool $cascade): string
    {
        $sql = parent::_drop_table($table, $if_exists, $cascade);
        if ($cascade) {
            $sql .= ' CASCADE';
        }
        return $sql;
    }
    /**
     * Constructs sql to check if key is a constraint.
     */
    protected function _drop_key_as_constraint(string $table, string $constraint_name): string
    {
        return "SELECT con.conname\n               FROM pg_catalog.pg_constraint con\n                INNER JOIN pg_catalog.pg_class rel\n                           ON rel.oid = con.conrelid\n                INNER JOIN pg_catalog.pg_namespace nsp\n                           ON nsp.oid = connamespace\n               WHERE nsp.nspname = '{$this->db->schema}'\n                     AND rel.relname = '" . trim($table, '"') . "'\n                     AND con.conname = '" . trim($constraint_name, '"') . "'";
    }
}