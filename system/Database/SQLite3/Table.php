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

use Code_Igniter\Database\Exceptions\Data_Exception;
use stdClass;
/**
 * Provides missing features for altering tables that are common
 * in other supported databases, but are missing from SQLite.
 * These are needed in order to support migrations during testing
 * when another database is used as the primary engine, but
 * SQLite in memory databases are used for faster test execution.
 */
class Table
{
    /**
     * All of the fields this table represents.
     *
     * @var array<string, array<string, bool|int|string|null>> [name => attributes]
     */
    protected $fields = [];
    /**
     * All of the unique/primary keys in the table.
     *
     * @var array
     */
    protected $keys = [];
    /**
     * All of the foreign keys in the table.
     *
     * @var array
     */
    protected $foreign_keys = [];
    /**
     * The name of the table we're working with.
     *
     * @var string
     */
    protected $table_name;
    /**
     * The name of the table, with database prefix
     *
     * @var string
     */
    protected $prefixed_table_name;
    /**
     * Database connection.
     *
     * @var Connection
     */
    protected $db;
    /**
     * Handle to our forge.
     *
     * @var Forge
     */
    protected $forge;
    /**
     * Table constructor.
     */
    public function __construct(Connection $db, Forge $forge)
    {
        $this->db = $db;
        $this->forge = $forge;
    }
    /**
     * Reads an existing database table and
     * collects all of the information needed to
     * recreate this table.
     *
     * @return Table
     */
    public function from_table(string $table)
    {
        $this->prefixed_table_name = $table;
        $prefix = $this->db->db_prefix;
        if (!empty($prefix) && str_starts_with($table, $prefix)) {
            $table = substr($table, strlen($prefix));
        }
        if (!$this->db->table_exists($this->prefixed_table_name)) {
            throw Data_Exception::for_table_not_found($this->prefixed_table_name);
        }
        $this->table_name = $table;
        $this->fields = $this->format_fields($this->db->get_field_data($table));
        $this->keys = array_merge($this->keys, $this->format_keys($this->db->get_index_data($table)));
        // if primary key index exists twice then remove psuedo index name 'primary'.
        $primary_indexes = array_filter($this->keys, static fn($index): bool => $index['type'] === 'primary');
        if ($primary_indexes !== [] && count($primary_indexes) > 1 && array_key_exists('primary', $this->keys)) {
            unset($this->keys['primary']);
        }
        $this->foreign_keys = $this->db->get_foreign_key_data($table);
        return $this;
    }
    /**
     * Called after `fromTable` and any actions, like `dropColumn`, etc,
     * to finalize the action. It creates a temp table, creates the new
     * table with modifications, and copies the data over to the new table.
     * Resets the connection dataCache to be sure changes are collected.
     */
    public function run(): bool
    {
        $this->db->query('PRAGMA foreign_keys = OFF');
        $this->db->trans_start();
        $this->forge->rename_table($this->table_name, "temp_{$this->table_name}");
        $this->forge->reset();
        $this->create_table();
        $this->copy_data();
        $this->forge->drop_table("temp_{$this->table_name}");
        $success = $this->db->trans_complete();
        $this->db->query('PRAGMA foreign_keys = ON');
        $this->db->reset_data_cache();
        return $success;
    }
    /**
     * Drops columns from the table.
     *
     * @param list<string>|string $columns Column names to drop.
     *
     * @return Table
     */
    public function drop_column($columns)
    {
        if (is_string($columns)) {
            $columns = explode(',', $columns);
        }
        foreach ($columns as $column) {
            $column = trim($column);
            if (isset($this->fields[$column])) {
                unset($this->fields[$column]);
            }
        }
        return $this;
    }
    /**
     * Modifies a field, including changing data type, renaming, etc.
     *
     * @param list<array<string, bool|int|string|null>> $fieldsToModify
     *
     * @return Table
     */
    public function modify_column(array $fields_to_modify)
    {
        foreach ($fields_to_modify as $field) {
            $old_name = $field['name'];
            unset($field['name']);
            $this->fields[$old_name] = $field;
        }
        return $this;
    }
    /**
     * Drops the primary key
     */
    public function drop_primary_key(): Table
    {
        $primary_indexes = array_filter($this->keys, static fn($index): bool => strtolower($index['type']) === 'primary');
        foreach (array_keys($primary_indexes) as $key) {
            unset($this->keys[$key]);
        }
        return $this;
    }
    /**
     * Drops a foreign key from this table so that
     * it won't be recreated in the future.
     *
     * @return Table
     */
    public function drop_foreign_key(string $foreign_name)
    {
        if (empty($this->foreign_keys)) {
            return $this;
        }
        if (isset($this->foreign_keys[$foreign_name])) {
            unset($this->foreign_keys[$foreign_name]);
        }
        return $this;
    }
    /**
     * Adds primary key
     */
    public function add_primary_key(array $fields): Table
    {
        $primary_indexes = array_filter($this->keys, static fn($index): bool => strtolower($index['type']) === 'primary');
        // if primary key already exists we can't add another one
        if ($primary_indexes !== []) {
            return $this;
        }
        // add array to keys of fields
        $pk = ['fields' => $fields['fields'], 'type' => 'primary'];
        $this->keys['primary'] = $pk;
        return $this;
    }
    /**
     * Add a foreign key
     *
     * @return $this
     */
    public function add_foreign_key(array $foreign_keys)
    {
        $fk = [];
        // convert to object
        foreach ($foreign_keys as $row) {
            $obj = new stdClass();
            $obj->column_name = $row['field'];
            $obj->foreign_table_name = $row['referenceTable'];
            $obj->foreign_column_name = $row['referenceField'];
            $obj->on_delete = $row['onDelete'];
            $obj->on_update = $row['onUpdate'];
            $fk[] = $obj;
        }
        $this->foreign_keys = array_merge($this->foreign_keys, $fk);
        return $this;
    }
    /**
     * Creates the new table based on our current fields.
     *
     * @return bool
     */
    protected function create_table()
    {
        $this->drop_indexes();
        $this->db->reset_data_cache();
        // Handle any modified columns.
        $fields = [];
        foreach ($this->fields as $name => $field) {
            if (isset($field['new_name'])) {
                $fields[$field['new_name']] = $field;
                continue;
            }
            $fields[$name] = $field;
        }
        $this->forge->add_field($fields);
        $field_names = array_keys($fields);
        $this->keys = array_filter($this->keys, static fn($index): bool => count(array_intersect($index['fields'], $field_names)) === count($index['fields']));
        // Unique/Index keys
        if (is_array($this->keys)) {
            foreach ($this->keys as $key_name => $key) {
                switch ($key['type']) {
                    case 'primary':
                        $this->forge->add_primary_key($key['fields']);
                        break;
                    case 'unique':
                        $this->forge->add_unique_key($key['fields'], $key_name);
                        break;
                    case 'index':
                        $this->forge->add_key($key['fields'], false, false, $key_name);
                        break;
                }
            }
        }
        foreach ($this->foreign_keys as $foreign_key) {
            $this->forge->add_foreign_key($foreign_key->column_name, trim($foreign_key->foreign_table_name, $this->db->db_prefix), $foreign_key->foreign_column_name);
        }
        return $this->forge->create_table($this->table_name);
    }
    /**
     * Copies data from our old table to the new one,
     * taking care map data correctly based on any columns
     * that have been renamed.
     *
     * @return void
     */
    protected function copy_data()
    {
        $ex_fields = [];
        $new_fields = [];
        foreach ($this->fields as $name => $details) {
            $new_fields[] = $details['new_name'] ?? $name;
            $ex_fields[] = $name;
        }
        $ex_fields = implode(', ', array_map(fn($item) => $this->db->protect_identifiers($item), $ex_fields));
        $new_fields = implode(', ', array_map(fn($item) => $this->db->protect_identifiers($item), $new_fields));
        $this->db->query("INSERT INTO {$this->prefixed_table_name}({$new_fields}) SELECT {$ex_fields} FROM {$this->db->db_prefix}temp_{$this->table_name}");
    }
    /**
     * Converts fields retrieved from the database to
     * the format needed for creating fields with Forge.
     *
     * @param array|bool $fields
     *
     * @return ($fields is array ? array : mixed)
     */
    protected function format_fields($fields)
    {
        if (!is_array($fields)) {
            return $fields;
        }
        $return = [];
        foreach ($fields as $field) {
            $return[$field->name] = ['type' => $field->type, 'default' => $field->default, 'null' => $field->nullable];
            if ($field->default === null) {
                // `null` means that the default value is not defined.
                unset($return[$field->name]['default']);
            } elseif ($field->default === 'NULL') {
                // 'NULL' means that the default value is NULL.
                $return[$field->name]['default'] = null;
            } else {
                $default = trim($field->default, "'");
                if ($this->is_integer_type($field->type)) {
                    $default = (int) $default;
                } elseif ($this->is_numeric_type($field->type)) {
                    $default = (float) $default;
                }
                $return[$field->name]['default'] = $default;
            }
            if ($field->primary_key) {
                $this->keys['primary'] = ['fields' => [$field->name], 'type' => 'primary'];
            }
        }
        return $return;
    }
    /**
     * Is INTEGER type?
     *
     * @param string $type SQLite data type (case-insensitive)
     *
     * @see https://www.sqlite.org/datatype3.html
     */
    private function is_integer_type(string $type): bool
    {
        return str_contains(strtoupper($type), 'INT');
    }
    /**
     * Is NUMERIC type?
     *
     * @param string $type SQLite data type (case-insensitive)
     *
     * @see https://www.sqlite.org/datatype3.html
     */
    private function is_numeric_type(string $type): bool
    {
        return in_array(strtoupper($type), ['NUMERIC', 'DECIMAL'], true);
    }
    /**
     * Converts keys retrieved from the database to
     * the format needed to create later.
     *
     * @param array<string, stdClass> $keys
     *
     * @return array<string, array{fields: string, type: string}>
     */
    protected function format_keys($keys)
    {
        $return = [];
        foreach ($keys as $name => $key) {
            $return[strtolower($name)] = ['fields' => $key->fields, 'type' => strtolower($key->type)];
        }
        return $return;
    }
    /**
     * Attempts to drop all indexes and constraints
     * from the database for this table.
     *
     * @return void
     */
    protected function drop_indexes()
    {
        if (!is_array($this->keys) || $this->keys === []) {
            return;
        }
        foreach (array_keys($this->keys) as $name) {
            if ($name === 'primary') {
                continue;
            }
            $this->db->query("DROP INDEX IF EXISTS '{$name}'");
        }
    }
}