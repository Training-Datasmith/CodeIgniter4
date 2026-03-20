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
use Code_Igniter\Database\Forge as BaseForge;
/**
 * Forge for SQLite3
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
     * @var Connection
     */
    protected $db;
    /**
     * UNSIGNED support
     *
     * @var array|bool
     */
    protected $_unsigned = false;
    /**
     * NULL value representation in CREATE/ALTER TABLE statements
     *
     * @var string
     *
     * @internal
     */
    protected $null = 'NULL';
    /**
     * Constructor.
     */
    public function __construct(Base_Connection $db)
    {
        parent::__construct($db);
        if (version_compare($this->db->get_version(), '3.3', '<')) {
            $this->drop_table_if_str = false;
        }
    }
    /**
     * Create database
     *
     * @param bool $ifNotExists Whether to add IF NOT EXISTS condition
     */
    public function create_database(string $db_name, bool $if_not_exists = false): bool
    {
        // In SQLite, a database is created when you connect to the database.
        // We'll return TRUE so that an error isn't generated.
        return true;
    }
    /**
     * Drop database
     *
     * @throws DatabaseException
     */
    public function drop_database(string $db_name): bool
    {
        // In SQLite, a database is dropped when we delete a file
        if (!is_file($db_name)) {
            if ($this->db->db_debug) {
                throw new Database_Exception('Unable to drop the specified database.');
            }
            return false;
        }
        // We need to close the pseudo-connection first
        $this->db->close();
        if (!@unlink($db_name)) {
            if ($this->db->db_debug) {
                throw new Database_Exception('Unable to drop the specified database.');
            }
            return false;
        }
        if (!empty($this->db->data_cache['db_names'])) {
            $key = array_search(strtolower($db_name), array_map(strtolower(...), $this->db->data_cache['db_names']), true);
            if ($key !== false) {
                unset($this->db->data_cache['db_names'][$key]);
            }
        }
        return true;
    }
    /**
     * @param list<string>|string $columnNames
     *
     * @throws DatabaseException
     */
    public function drop_column(string $table, $column_names): bool
    {
        $columns = is_array($column_names) ? $column_names : array_map(trim(...), explode(',', $column_names));
        $result = (new Table($this->db, $this))->from_table($this->db->db_prefix . $table)->drop_column($columns)->run();
        if (!$result && $this->db->db_debug) {
            throw new Database_Exception(sprintf('Failed to drop column%s "%s" on "%s" table.', count($columns) > 1 ? 's' : '', implode('", "', $columns), $table));
        }
        return $result;
    }
    /**
     * @param array|string $processedFields Processed column definitions
     *                                      or column names to DROP
     *
     * @return ($alterType is 'DROP' ? string : list<string>|null)
     */
    protected function _alter_table(string $alter_type, string $table, $processed_fields)
    {
        switch ($alter_type) {
            case 'CHANGE':
                $fields_to_modify = [];
                foreach ($processed_fields as $processed_field) {
                    $name = $processed_field['name'];
                    $new_name = $processed_field['new_name'];
                    $field = $this->fields[$name];
                    $field['name'] = $name;
                    $field['new_name'] = $new_name;
                    // Unlike when creating a table, if `null` is not specified,
                    // the column will be `NULL`, not `NOT NULL`.
                    if ($processed_field['null'] === '') {
                        $field['null'] = true;
                    }
                    $fields_to_modify[] = $field;
                }
                (new Table($this->db, $this))->from_table($table)->modify_column($fields_to_modify)->run();
                return null;
            // Why null?
            default:
                return parent::_alter_table($alter_type, $table, $processed_fields);
        }
    }
    /**
     * Process column
     */
    protected function _process_column(array $processed_field): string
    {
        if ($processed_field['type'] === 'TEXT' && str_starts_with($processed_field['length'], "('")) {
            $processed_field['type'] .= ' CHECK(' . $this->db->escape_identifiers($processed_field['name']) . ' IN ' . $processed_field['length'] . ')';
        }
        return $this->db->escape_identifiers($processed_field['name']) . ' ' . $processed_field['type'] . $processed_field['auto_increment'] . $processed_field['null'] . $processed_field['unique'] . $processed_field['default'];
    }
    /**
     * Field attribute TYPE
     *
     * Performs a data type mapping between different databases.
     */
    protected function _attribute_type(array &$attributes)
    {
        switch (strtoupper($attributes['TYPE'])) {
            case 'ENUM':
            case 'SET':
                $attributes['TYPE'] = 'TEXT';
                break;
            case 'BOOLEAN':
                $attributes['TYPE'] = 'INT';
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
        if (!empty($attributes['AUTO_INCREMENT']) && $attributes['AUTO_INCREMENT'] === true && str_contains(strtolower($field['type']), 'int')) {
            $field['type'] = 'INTEGER PRIMARY KEY';
            $field['default'] = '';
            $field['null'] = '';
            $field['unique'] = '';
            $field['auto_increment'] = ' AUTOINCREMENT';
            $this->primary_keys = [];
        }
    }
    /**
     * Foreign Key Drop
     *
     * @throws DatabaseException
     */
    public function drop_foreign_key(string $table, string $foreign_name): bool
    {
        // If this version of SQLite doesn't support it, we're done here
        if ($this->db->supports_foreign_keys() !== true) {
            return true;
        }
        // Otherwise we have to copy the table and recreate
        // without the foreign key being involved now
        $sql_table = new Table($this->db, $this);
        return $sql_table->from_table($this->db->db_prefix . $table)->drop_foreign_key($foreign_name)->run();
    }
    /**
     * Drop Primary Key
     */
    public function drop_primary_key(string $table, string $key_name = ''): bool
    {
        $sql_table = new Table($this->db, $this);
        return $sql_table->from_table($this->db->db_prefix . $table)->drop_primary_key()->run();
    }
    public function add_foreign_key($field_name = '', string $table_name = '', $table_field = '', string $on_update = '', string $on_delete = '', string $fk_name = ''): Base_Forge
    {
        if ($fk_name === '') {
            return parent::add_foreign_key($field_name, $table_name, $table_field, $on_update, $on_delete, $fk_name);
        }
        throw new Database_Exception('SQLite does not support foreign key names. CodeIgniter will refer to them in the format: prefix_table_column_referencecolumn_foreign');
    }
    /**
     * Generates SQL to add primary key
     *
     * @param bool $asQuery When true recreates table with key, else partial SQL used with CREATE TABLE
     */
    protected function _process_primary_keys(string $table, bool $as_query = false): string
    {
        if ($as_query === false) {
            return parent::_process_primary_keys($table, $as_query);
        }
        $sql_table = new Table($this->db, $this);
        $sql_table->from_table($this->db->db_prefix . $table)->add_primary_key($this->primary_keys)->run();
        return '';
    }
    /**
     * Generates SQL to add foreign keys
     *
     * @param bool $asQuery When true recreates table with key, else partial SQL used with CREATE TABLE
     */
    protected function _process_foreign_keys(string $table, bool $as_query = false): array
    {
        if ($as_query === false) {
            return parent::_process_foreign_keys($table, $as_query);
        }
        $error_names = [];
        foreach ($this->foreign_keys as $name) {
            foreach ($name['field'] as $f) {
                if (!isset($this->fields[$f])) {
                    $error_names[] = $f;
                }
            }
        }
        if ($error_names !== []) {
            $error_names = [implode(', ', $error_names)];
            throw new Database_Exception(lang('Database.fieldNotExists', $error_names));
        }
        $sql_table = new Table($this->db, $this);
        $sql_table->from_table($this->db->db_prefix . $table)->add_foreign_key($this->foreign_keys)->run();
        return [];
    }
}