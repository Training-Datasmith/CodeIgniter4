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
namespace Code_Igniter\Database;

use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\Exceptions\RuntimeException;
use Throwable;
/**
 * The Forge class transforms migrations to executable
 * SQL statements.
 */
class Forge
{
    /**
     * The active database connection.
     *
     * @var BaseConnection
     */
    protected $db;
    /**
     * List of fields in the form `[name => attributes]`
     *
     * @var array<string, array<string, bool|string>|string>
     */
    protected $fields = [];
    /**
     * List of keys.
     *
     * @var list<array{fields?: list<string>, keyName?: string}>
     */
    protected $keys = [];
    /**
     * List of unique keys.
     *
     * @var array
     */
    protected $unique_keys = [];
    /**
     * Primary keys.
     *
     * @var array{fields?: list<string>, keyName?: string}
     */
    protected $primary_keys = [];
    /**
     * List of foreign keys.
     *
     * @var array
     */
    protected $foreign_keys = [];
    /**
     * Character set used.
     *
     * @var string
     */
    protected $charset = '';
    /**
     * CREATE DATABASE statement
     *
     * @var false|string
     */
    protected $create_database_str = 'CREATE DATABASE %s';
    /**
     * CREATE DATABASE IF statement
     *
     * @var string
     */
    protected $create_database_if_str;
    /**
     * CHECK DATABASE EXIST statement
     *
     * @var string
     */
    protected $check_database_exist_str;
    /**
     * DROP DATABASE statement
     *
     * @var false|string
     */
    protected $drop_database_str = 'DROP DATABASE %s';
    /**
     * CREATE TABLE statement
     *
     * @var string
     */
    protected $create_table_str = "%s %s (%s\n)";
    /**
     * CREATE TABLE IF statement
     *
     * @var bool|string
     *
     * @deprecated This is no longer used.
     */
    protected $create_table_if_str = 'CREATE TABLE IF NOT EXISTS';
    /**
     * CREATE TABLE keys flag
     *
     * Whether table keys are created from within the
     * CREATE TABLE statement.
     *
     * @var bool
     */
    protected $create_table_keys = false;
    /**
     * DROP TABLE IF EXISTS statement
     *
     * @var bool|string
     */
    protected $drop_table_if_str = 'DROP TABLE IF EXISTS';
    /**
     * RENAME TABLE statement
     *
     * @var false|string
     */
    protected $rename_table_str = 'ALTER TABLE %s RENAME TO %s';
    /**
     * UNSIGNED support
     *
     * @var array|bool
     */
    protected $unsigned = true;
    /**
     * NULL value representation in CREATE/ALTER TABLE statements
     *
     * @var string
     *
     * @internal Used for marking nullable fields. Not covered by BC promise.
     */
    protected $null = 'NULL';
    /**
     * DEFAULT value representation in CREATE/ALTER TABLE statements
     *
     * @var false|string
     */
    protected $default = ' DEFAULT ';
    /**
     * DROP CONSTRAINT statement
     *
     * @var string
     */
    protected $drop_constraint_str;
    /**
     * DROP INDEX statement
     *
     * @var string
     */
    protected $drop_index_str = 'DROP INDEX %s ON %s';
    /**
     * Foreign Key Allowed Actions
     *
     * @var array
     */
    protected $fk_allow_actions = ['CASCADE', 'SET NULL', 'NO ACTION', 'RESTRICT', 'SET DEFAULT'];
    /**
     * Constructor.
     */
    public function __construct(Base_Connection $db)
    {
        $this->db = $db;
    }
    /**
     * Provides access to the forge's current database connection.
     *
     * @return ConnectionInterface
     */
    public function get_connection()
    {
        return $this->db;
    }
    /**
     * Create database
     *
     * @param bool $ifNotExists Whether to add IF NOT EXISTS condition
     *
     * @throws DatabaseException
     */
    public function create_database(string $db_name, bool $if_not_exists = false): bool
    {
        if ($if_not_exists && $this->create_database_if_str === null) {
            if ($this->database_exists($db_name)) {
                return true;
            }
            $if_not_exists = false;
        }
        if ($this->create_database_str === false) {
            if ($this->db->db_debug) {
                throw new Database_Exception('This feature is not available for the database you are using.');
            }
            return false;
            // @codeCoverageIgnore
        }
        try {
            if (!$this->db->query(sprintf($if_not_exists ? $this->create_database_if_str : $this->create_database_str, $this->db->escape_identifier($db_name), $this->db->charset, $this->db->db_collat))) {
                // @codeCoverageIgnoreStart
                if ($this->db->db_debug) {
                    throw new Database_Exception('Unable to create the specified database.');
                }
                return false;
                // @codeCoverageIgnoreEnd
            }
            if (!empty($this->db->data_cache['db_names'])) {
                $this->db->data_cache['db_names'][] = $db_name;
            }
            return true;
        } catch (Throwable $e) {
            if ($this->db->db_debug) {
                throw new Database_Exception('Unable to create the specified database.', 0, $e);
            }
            return false;
            // @codeCoverageIgnore
        }
    }
    /**
     * Determine if a database exists
     *
     * @throws DatabaseException
     */
    private function database_exists(string $db_name): bool
    {
        if ($this->check_database_exist_str === null) {
            if ($this->db->db_debug) {
                throw new Database_Exception('This feature is not available for the database you are using.');
            }
            return false;
        }
        return $this->db->query($this->check_database_exist_str, $db_name)->get_row() !== null;
    }
    /**
     * Drop database
     *
     * @throws DatabaseException
     */
    public function drop_database(string $db_name): bool
    {
        if ($this->drop_database_str === false) {
            if ($this->db->db_debug) {
                throw new Database_Exception('This feature is not available for the database you are using.');
            }
            return false;
        }
        if (!$this->db->query(sprintf($this->drop_database_str, $this->db->escape_identifier($db_name)))) {
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
     * Add Key
     *
     * @param array|string $key
     *
     * @return Forge
     */
    public function add_key($key, bool $primary = false, bool $unique = false, string $key_name = '')
    {
        if ($primary) {
            $this->primary_keys = ['fields' => (array) $key, 'keyName' => $key_name];
        } else {
            $this->keys[] = ['fields' => (array) $key, 'keyName' => $key_name];
            if ($unique) {
                $this->unique_keys[] = count($this->keys) - 1;
            }
        }
        return $this;
    }
    /**
     * Add Primary Key
     *
     * @param array|string $key
     *
     * @return Forge
     */
    public function add_primary_key($key, string $key_name = '')
    {
        return $this->add_key($key, true, false, $key_name);
    }
    /**
     * Add Unique Key
     *
     * @param array|string $key
     *
     * @return Forge
     */
    public function add_unique_key($key, string $key_name = '')
    {
        return $this->add_key($key, false, true, $key_name);
    }
    /**
     * Add Field
     *
     * @param array<string, array|string>|string $fields Field array or Field string
     *
     * @return Forge
     */
    public function add_field($fields)
    {
        if (is_string($fields)) {
            if ($fields === 'id') {
                $this->add_field(['id' => ['type' => 'INT', 'constraint' => 9, 'auto_increment' => true]]);
                $this->add_key('id', true);
            } else {
                if (!str_contains($fields, ' ')) {
                    throw new InvalidArgumentException('Field information is required for that operation.');
                }
                $field_name = explode(' ', $fields, 2)[0];
                $field_name = trim($field_name, '`\'"');
                $this->fields[$field_name] = $fields;
            }
        }
        if (is_array($fields)) {
            foreach ($fields as $name => $attributes) {
                if (is_string($attributes)) {
                    $this->add_field($attributes);
                    continue;
                }
                if (is_array($attributes)) {
                    $this->fields = array_merge($this->fields, [$name => $attributes]);
                }
            }
        }
        return $this;
    }
    /**
     * Add Foreign Key
     *
     * @param list<string>|string $fieldName
     * @param list<string>|string $tableField
     *
     * @throws DatabaseException
     */
    public function add_foreign_key($field_name = '', string $table_name = '', $table_field = '', string $on_update = '', string $on_delete = '', string $fk_name = ''): Forge
    {
        $field_name = (array) $field_name;
        $table_field = (array) $table_field;
        $this->foreign_keys[] = ['field' => $field_name, 'referenceTable' => $table_name, 'referenceField' => $table_field, 'onDelete' => strtoupper($on_delete), 'onUpdate' => strtoupper($on_update), 'fkName' => $fk_name];
        return $this;
    }
    /**
     * Drop Key
     *
     * @throws DatabaseException
     */
    public function drop_key(string $table, string $key_name, bool $prefix_key_name = true): bool
    {
        $key_name = $this->db->escape_identifiers(($prefix_key_name ? $this->db->db_prefix : '') . $key_name);
        $table = $this->db->escape_identifiers($this->db->db_prefix . $table);
        $drop_key_as_constraint = $this->drop_key_as_constraint($table, $key_name);
        if ($drop_key_as_constraint) {
            $sql = sprintf($this->drop_constraint_str, $table, $key_name);
        } else {
            $sql = sprintf($this->drop_index_str, $key_name, $table);
        }
        if ($sql === '') {
            if ($this->db->db_debug) {
                throw new Database_Exception('This feature is not available for the database you are using.');
            }
            return false;
        }
        return $this->db->query($sql);
    }
    /**
     * Checks if key needs to be dropped as a constraint.
     */
    protected function drop_key_as_constraint(string $table, string $constraint_name): bool
    {
        $sql = $this->_drop_key_as_constraint($table, $constraint_name);
        if ($sql === '') {
            return false;
        }
        return $this->db->query($sql)->get_result_array() !== [];
    }
    /**
     * Constructs sql to check if key is a constraint.
     */
    protected function _drop_key_as_constraint(string $table, string $constraint_name): string
    {
        return '';
    }
    /**
     * Drop Primary Key
     */
    public function drop_primary_key(string $table, string $key_name = ''): bool
    {
        $sql = sprintf('ALTER TABLE %s DROP CONSTRAINT %s', $this->db->escape_identifiers($this->db->db_prefix . $table), $key_name === '' ? $this->db->escape_identifiers('pk_' . $this->db->db_prefix . $table) : $this->db->escape_identifiers($key_name));
        return $this->db->query($sql);
    }
    /**
     * @return bool
     *
     * @throws DatabaseException
     */
    public function drop_foreign_key(string $table, string $foreign_name)
    {
        $sql = sprintf((string) $this->drop_constraint_str, $this->db->escape_identifiers($this->db->db_prefix . $table), $this->db->escape_identifiers($foreign_name));
        if ($sql === '') {
            if ($this->db->db_debug) {
                throw new Database_Exception('This feature is not available for the database you are using.');
            }
            return false;
        }
        return $this->db->query($sql);
    }
    /**
     * @param array $attributes Table attributes
     *
     * @return bool
     *
     * @throws DatabaseException
     */
    public function create_table(string $table, bool $if_not_exists = false, array $attributes = [])
    {
        if ($table === '') {
            throw new InvalidArgumentException('A table name is required for that operation.');
        }
        $table = $this->db->db_prefix . $table;
        if ($this->fields === []) {
            throw new RuntimeException('Field information is required.');
        }
        // If table exists lets stop here
        if ($if_not_exists && $this->db->table_exists($table, false)) {
            $this->reset();
            return true;
        }
        $sql = $this->_create_table($table, false, $attributes);
        if (($result = $this->db->query($sql)) !== false) {
            if (isset($this->db->data_cache['table_names']) && !in_array($table, $this->db->data_cache['table_names'], true)) {
                $this->db->data_cache['table_names'][] = $table;
            }
            // Most databases don't support creating indexes from within the CREATE TABLE statement
            if ($this->keys !== []) {
                for ($i = 0, $sqls = $this->_process_indexes($table), $c = count($sqls); $i < $c; $i++) {
                    $this->db->query($sqls[$i]);
                }
            }
        }
        $this->reset();
        return $result;
    }
    /**
     * @param array $attributes Table attributes
     *
     * @return string SQL string
     *
     * @deprecated $ifNotExists is no longer used, and will be removed.
     */
    protected function _create_table(string $table, bool $if_not_exists, array $attributes)
    {
        $processed_fields = $this->_process_fields(true);
        for ($i = 0, $c = count($processed_fields); $i < $c; $i++) {
            $processed_fields[$i] = $processed_fields[$i]['_literal'] !== false ? "\n\t" . $processed_fields[$i]['_literal'] : "\n\t" . $this->_process_column($processed_fields[$i]);
        }
        $processed_fields = implode(',', $processed_fields);
        $processed_fields .= $this->_process_primary_keys($table);
        $processed_fields .= current($this->_process_foreign_keys($table));
        if ($this->create_table_keys === true) {
            $indexes = current($this->_process_indexes($table));
            if (is_string($indexes)) {
                $processed_fields .= $indexes;
            }
        }
        return sprintf($this->create_table_str . '%s', 'CREATE TABLE', $this->db->escape_identifiers($table), $processed_fields, $this->_create_table_attributes($attributes));
    }
    protected function _create_table_attributes(array $attributes): string
    {
        $sql = '';
        foreach (array_keys($attributes) as $key) {
            if (is_string($key)) {
                $sql .= ' ' . strtoupper($key) . ' ' . $this->db->escape($attributes[$key]);
            }
        }
        return $sql;
    }
    /**
     * @return bool
     *
     * @throws DatabaseException
     */
    public function drop_table(string $table_name, bool $if_exists = false, bool $cascade = false)
    {
        if ($table_name === '') {
            if ($this->db->db_debug) {
                throw new Database_Exception('A table name is required for that operation.');
            }
            return false;
        }
        if ($this->db->db_prefix !== '' && str_starts_with($table_name, $this->db->db_prefix)) {
            $table_name = substr($table_name, strlen($this->db->db_prefix));
        }
        if (($query = $this->_drop_table($this->db->db_prefix . $table_name, $if_exists, $cascade)) === true) {
            return true;
        }
        $this->db->disable_foreign_key_checks();
        $query = $this->db->query($query);
        $this->db->enable_foreign_key_checks();
        if ($query && !empty($this->db->data_cache['table_names'])) {
            $key = array_search(strtolower($this->db->db_prefix . $table_name), array_map(strtolower(...), $this->db->data_cache['table_names']), true);
            if ($key !== false) {
                unset($this->db->data_cache['table_names'][$key]);
            }
        }
        return $query;
    }
    /**
     * Generates a platform-specific DROP TABLE string
     *
     * @return bool|string
     */
    protected function _drop_table(string $table, bool $if_exists, bool $cascade)
    {
        $sql = 'DROP TABLE';
        if ($if_exists) {
            if ($this->drop_table_if_str === false) {
                if (!$this->db->table_exists($table)) {
                    return true;
                }
            } else {
                $sql = sprintf($this->drop_table_if_str, $this->db->escape_identifiers($table));
            }
        }
        return $sql . ' ' . $this->db->escape_identifiers($table);
    }
    /**
     * @return bool
     *
     * @throws DatabaseException
     */
    public function rename_table(string $table_name, string $new_table_name)
    {
        if ($table_name === '' || $new_table_name === '') {
            throw new InvalidArgumentException('A table name is required for that operation.');
        }
        if ($this->rename_table_str === false) {
            if ($this->db->db_debug) {
                throw new Database_Exception('This feature is not available for the database you are using.');
            }
            return false;
        }
        $result = $this->db->query(sprintf($this->rename_table_str, $this->db->escape_identifiers($this->db->db_prefix . $table_name), $this->db->escape_identifiers($this->db->db_prefix . $new_table_name)));
        if ($result && !empty($this->db->data_cache['table_names'])) {
            $key = array_search(strtolower($this->db->db_prefix . $table_name), array_map(strtolower(...), $this->db->data_cache['table_names']), true);
            if ($key !== false) {
                $this->db->data_cache['table_names'][$key] = $this->db->db_prefix . $new_table_name;
            }
        }
        return $result;
    }
    /**
     * @param array<string, array|string>|string $fields Field array or Field string
     *
     * @throws DatabaseException
     */
    public function add_column(string $table, $fields): bool
    {
        // Work-around for literal column definitions
        if (is_string($fields)) {
            $fields = [$fields];
        }
        foreach (array_keys($fields) as $name) {
            $this->add_field([$name => $fields[$name]]);
        }
        $sqls = $this->_alter_table('ADD', $this->db->db_prefix . $table, $this->_process_fields());
        $this->reset();
        if ($sqls === false) {
            if ($this->db->db_debug) {
                throw new Database_Exception('This feature is not available for the database you are using.');
            }
            return false;
        }
        foreach ($sqls as $sql) {
            if ($this->db->query($sql) === false) {
                return false;
            }
        }
        return true;
    }
    /**
     * @param list<string>|string $columnNames column names to DROP
     *
     * @return bool
     *
     * @throws DatabaseException
     */
    public function drop_column(string $table, $column_names)
    {
        $sql = $this->_alter_table('DROP', $this->db->db_prefix . $table, $column_names);
        if ($sql === false) {
            if ($this->db->db_debug) {
                throw new Database_Exception('This feature is not available for the database you are using.');
            }
            return false;
        }
        return $this->db->query($sql);
    }
    /**
     * @param array<string, array|string>|string $fields Field array or Field string
     *
     * @throws DatabaseException
     */
    public function modify_column(string $table, $fields): bool
    {
        // Work-around for literal column definitions
        if (is_string($fields)) {
            $fields = [$fields];
        }
        foreach (array_keys($fields) as $name) {
            $this->add_field([$name => $fields[$name]]);
        }
        if ($this->fields === []) {
            throw new RuntimeException('Field information is required');
        }
        $sqls = $this->_alter_table('CHANGE', $this->db->db_prefix . $table, $this->_process_fields());
        $this->reset();
        if ($sqls === false) {
            if ($this->db->db_debug) {
                throw new Database_Exception('This feature is not available for the database you are using.');
            }
            return false;
        }
        if (is_array($sqls)) {
            foreach ($sqls as $sql) {
                if ($this->db->query($sql) === false) {
                    return false;
                }
            }
        }
        return true;
    }
    /**
     * @param 'ADD'|'CHANGE'|'DROP' $alterType
     * @param array|string          $processedFields Processed column definitions
     *                                               or column names to DROP
     *
     * @return ($alterType is 'DROP' ? string : false|list<string>|null)
     */
    protected function _alter_table(string $alter_type, string $table, $processed_fields)
    {
        $sql = 'ALTER TABLE ' . $this->db->escape_identifiers($table) . ' ';
        // DROP has everything it needs now.
        if ($alter_type === 'DROP') {
            $column_names_to_drop = $processed_fields;
            if (is_string($column_names_to_drop)) {
                $column_names_to_drop = explode(',', $column_names_to_drop);
            }
            $column_names_to_drop = array_map(fn($field): string => 'DROP COLUMN ' . $this->db->escape_identifiers(trim($field)), $column_names_to_drop);
            return $sql . implode(', ', $column_names_to_drop);
        }
        $sql .= $alter_type === 'ADD' ? 'ADD ' : $alter_type . ' COLUMN ';
        $sqls = [];
        foreach ($processed_fields as $field) {
            $sqls[] = $sql . ($field['_literal'] !== false ? $field['_literal'] : $this->_process_column($field));
        }
        return $sqls;
    }
    /**
     * Returns $processedFields array from $this->fields data.
     */
    protected function _process_fields(bool $create_table = false): array
    {
        $processed_fields = [];
        foreach ($this->fields as $name => $attributes) {
            if (!is_array($attributes)) {
                $processed_fields[] = ['_literal' => $attributes];
                continue;
            }
            $attributes = array_change_key_case($attributes, CASE_UPPER);
            if ($create_table && empty($attributes['TYPE'])) {
                continue;
            }
            if (isset($attributes['TYPE'])) {
                $this->_attribute_type($attributes);
            }
            $field = ['name' => $name, 'new_name' => $attributes['NAME'] ?? null, 'type' => $attributes['TYPE'] ?? null, 'length' => '', 'unsigned' => '', 'null' => '', 'unique' => '', 'default' => '', 'auto_increment' => '', '_literal' => false];
            if (isset($attributes['TYPE'])) {
                $this->_attribute_unsigned($attributes, $field);
            }
            if ($create_table === false) {
                if (isset($attributes['AFTER'])) {
                    $field['after'] = $attributes['AFTER'];
                } elseif (isset($attributes['FIRST'])) {
                    $field['first'] = (bool) $attributes['FIRST'];
                }
            }
            $this->_attribute_default($attributes, $field);
            if (isset($attributes['NULL'])) {
                $null_string = ' ' . $this->null;
                if ($attributes['NULL'] === true) {
                    $field['null'] = empty($this->null) ? '' : $null_string;
                } elseif ($attributes['NULL'] === $null_string) {
                    $field['null'] = $null_string;
                } elseif ($attributes['NULL'] === '') {
                    $field['null'] = '';
                } else {
                    $field['null'] = ' NOT ' . $this->null;
                }
            } elseif ($create_table) {
                $field['null'] = ' NOT ' . $this->null;
            }
            $this->_attribute_auto_increment($attributes, $field);
            $this->_attribute_unique($attributes, $field);
            if (isset($attributes['COMMENT'])) {
                $field['comment'] = $this->db->escape($attributes['COMMENT']);
            }
            if (isset($attributes['TYPE']) && !empty($attributes['CONSTRAINT'])) {
                if (is_array($attributes['CONSTRAINT'])) {
                    $attributes['CONSTRAINT'] = $this->db->escape($attributes['CONSTRAINT']);
                    $attributes['CONSTRAINT'] = implode(',', $attributes['CONSTRAINT']);
                }
                $field['length'] = '(' . $attributes['CONSTRAINT'] . ')';
            }
            $processed_fields[] = $field;
        }
        return $processed_fields;
    }
    /**
     * Converts $processedField array to field definition string.
     */
    protected function _process_column(array $processed_field): string
    {
        return $this->db->escape_identifiers($processed_field['name']) . ' ' . $processed_field['type'] . $processed_field['length'] . $processed_field['unsigned'] . $processed_field['default'] . $processed_field['null'] . $processed_field['auto_increment'] . $processed_field['unique'];
    }
    /**
     * Performs a data type mapping between different databases.
     *
     * @return void
     */
    protected function _attribute_type(array &$attributes)
    {
        // Usually overridden by drivers
    }
    /**
     * Depending on the unsigned property value:
     *
     *    - TRUE will always set $field['unsigned'] to 'UNSIGNED'
     *    - FALSE will always set $field['unsigned'] to ''
     *    - array(TYPE) will set $field['unsigned'] to 'UNSIGNED',
     *        if $attributes['TYPE'] is found in the array
     *    - array(TYPE => UTYPE) will change $field['type'],
     *        from TYPE to UTYPE in case of a match
     *
     * @return void
     */
    protected function _attribute_unsigned(array &$attributes, array &$field)
    {
        if (empty($attributes['UNSIGNED']) || $attributes['UNSIGNED'] !== true) {
            return;
        }
        // Reset the attribute in order to avoid issues if we do type conversion
        $attributes['UNSIGNED'] = false;
        if (is_array($this->unsigned)) {
            foreach (array_keys($this->unsigned) as $key) {
                if (is_int($key) && strcasecmp($attributes['TYPE'], $this->unsigned[$key]) === 0) {
                    $field['unsigned'] = ' UNSIGNED';
                    return;
                }
                if (is_string($key) && strcasecmp($attributes['TYPE'], $key) === 0) {
                    $field['type'] = $key;
                    return;
                }
            }
            return;
        }
        $field['unsigned'] = $this->unsigned === true ? ' UNSIGNED' : '';
    }
    /**
     * @return void
     */
    protected function _attribute_default(array &$attributes, array &$field)
    {
        if ($this->default === false) {
            return;
        }
        if (array_key_exists('DEFAULT', $attributes)) {
            if ($attributes['DEFAULT'] === null) {
                $field['default'] = empty($this->null) ? '' : $this->default . $this->null;
                // Override the NULL attribute if that's our default
                $attributes['NULL'] = true;
                $field['null'] = empty($this->null) ? '' : ' ' . $this->null;
            } elseif ($attributes['DEFAULT'] instanceof Raw_Sql) {
                $field['default'] = $this->default . $attributes['DEFAULT'];
            } else {
                $field['default'] = $this->default . $this->db->escape($attributes['DEFAULT']);
            }
        }
    }
    /**
     * @return void
     */
    protected function _attribute_unique(array &$attributes, array &$field)
    {
        if (!empty($attributes['UNIQUE']) && $attributes['UNIQUE'] === true) {
            $field['unique'] = ' UNIQUE';
        }
    }
    /**
     * @return void
     */
    protected function _attribute_auto_increment(array &$attributes, array &$field)
    {
        if (!empty($attributes['AUTO_INCREMENT']) && $attributes['AUTO_INCREMENT'] === true && str_contains(strtolower($field['type']), 'int')) {
            $field['auto_increment'] = ' AUTO_INCREMENT';
        }
    }
    /**
     * Generates SQL to add primary key
     *
     * @param bool $asQuery When true returns stand alone SQL, else partial SQL used with CREATE TABLE
     */
    protected function _process_primary_keys(string $table, bool $as_query = false): string
    {
        $sql = '';
        if (isset($this->primary_keys['fields'])) {
            for ($i = 0, $c = count($this->primary_keys['fields']); $i < $c; $i++) {
                if (!isset($this->fields[$this->primary_keys['fields'][$i]])) {
                    unset($this->primary_keys['fields'][$i]);
                }
            }
        }
        if (isset($this->primary_keys['fields']) && $this->primary_keys['fields'] !== []) {
            if ($as_query) {
                $sql .= 'ALTER TABLE ' . $this->db->escape_identifiers($this->db->db_prefix . $table) . ' ADD ';
            } else {
                $sql .= ",\n\t";
            }
            $sql .= 'CONSTRAINT ' . $this->db->escape_identifiers($this->primary_keys['keyName'] === '' ? 'pk_' . $table : $this->primary_keys['keyName']) . ' PRIMARY KEY(' . implode(', ', $this->db->escape_identifiers($this->primary_keys['fields'])) . ')';
        }
        return $sql;
    }
    /**
     * Executes Sql to add indexes without createTable
     */
    public function process_indexes(string $table): bool
    {
        $sqls = [];
        $fk = $this->foreign_keys;
        if ($this->fields === []) {
            $field_data = $this->db->get_field_data($this->db->db_prefix . $table);
            $this->fields = array_combine(array_map(static fn($column_name) => $column_name->name, $field_data), array_fill(0, count($field_data), []));
        }
        $fields = $this->fields;
        if ($this->keys !== []) {
            $sqls = $this->_process_indexes($this->db->db_prefix . $table, true);
        }
        if ($this->primary_keys !== []) {
            $sqls[] = $this->_process_primary_keys($table, true);
        }
        $this->foreign_keys = $fk;
        $this->fields = $fields;
        if ($this->foreign_keys !== []) {
            $sqls = array_merge($sqls, $this->_process_foreign_keys($table, true));
        }
        foreach ($sqls as $sql) {
            if ($this->db->query($sql) === false) {
                return false;
            }
        }
        $this->reset();
        return true;
    }
    /**
     * Generates SQL to add indexes
     *
     * @param bool $asQuery When true returns stand alone SQL, else partial SQL used with CREATE TABLE
     */
    protected function _process_indexes(string $table, bool $as_query = false): array
    {
        $sqls = [];
        for ($i = 0, $c = count($this->keys); $i < $c; $i++) {
            for ($i2 = 0, $c2 = count($this->keys[$i]['fields']); $i2 < $c2; $i2++) {
                if (!isset($this->fields[$this->keys[$i]['fields'][$i2]])) {
                    unset($this->keys[$i]['fields'][$i2]);
                }
            }
            if (count($this->keys[$i]['fields']) <= 0) {
                continue;
            }
            $key_name = $this->db->escape_identifiers($this->keys[$i]['keyName'] === '' ? $table . '_' . implode('_', $this->keys[$i]['fields']) : $this->keys[$i]['keyName']);
            if (in_array($i, $this->unique_keys, true)) {
                if ($this->db->db_driver === 'SQLite3') {
                    $sqls[] = 'CREATE UNIQUE INDEX ' . $key_name . ' ON ' . $this->db->escape_identifiers($table) . ' (' . implode(', ', $this->db->escape_identifiers($this->keys[$i]['fields'])) . ')';
                } else {
                    $sqls[] = 'ALTER TABLE ' . $this->db->escape_identifiers($table) . ' ADD CONSTRAINT ' . $key_name . ' UNIQUE (' . implode(', ', $this->db->escape_identifiers($this->keys[$i]['fields'])) . ')';
                }
                continue;
            }
            $sqls[] = 'CREATE INDEX ' . $key_name . ' ON ' . $this->db->escape_identifiers($table) . ' (' . implode(', ', $this->db->escape_identifiers($this->keys[$i]['fields'])) . ')';
        }
        return $sqls;
    }
    /**
     * Generates SQL to add foreign keys
     *
     * @param bool $asQuery When true returns stand alone SQL, else partial SQL used with CREATE TABLE
     */
    protected function _process_foreign_keys(string $table, bool $as_query = false): array
    {
        $error_names = [];
        foreach ($this->foreign_keys as $fkey_info) {
            foreach ($fkey_info['field'] as $field_name) {
                if (!isset($this->fields[$field_name])) {
                    $error_names[] = $field_name;
                }
            }
        }
        if ($error_names !== []) {
            $error_names = [implode(', ', $error_names)];
            throw new Database_Exception(lang('Database.fieldNotExists', $error_names));
        }
        $sqls = [''];
        foreach ($this->foreign_keys as $index => $fkey) {
            if ($as_query === false) {
                $index = 0;
            } else {
                $sqls[$index] = '';
            }
            $name_index = $fkey['fkName'] !== '' ? $fkey['fkName'] : $table . '_' . implode('_', $fkey['field']) . ($this->db->db_driver === 'OCI8' ? '_fk' : '_foreign');
            $name_index_filled = $this->db->escape_identifiers($name_index);
            $foreign_key_filled = implode(', ', $this->db->escape_identifiers($fkey['field']));
            $reference_table_filled = $this->db->escape_identifiers($this->db->db_prefix . $fkey['referenceTable']);
            $reference_field_filled = implode(', ', $this->db->escape_identifiers($fkey['referenceField']));
            if ($as_query) {
                $sqls[$index] .= 'ALTER TABLE ' . $this->db->escape_identifiers($this->db->db_prefix . $table) . ' ADD ';
            } else {
                $sqls[$index] .= ",\n\t";
            }
            $format_sql = 'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(%s)';
            $sqls[$index] .= sprintf($format_sql, $name_index_filled, $foreign_key_filled, $reference_table_filled, $reference_field_filled);
            if ($fkey['onDelete'] !== false && in_array($fkey['onDelete'], $this->fk_allow_actions, true)) {
                $sqls[$index] .= ' ON DELETE ' . $fkey['onDelete'];
            }
            if ($this->db->db_driver !== 'OCI8' && $fkey['onUpdate'] !== false && in_array($fkey['onUpdate'], $this->fk_allow_actions, true)) {
                $sqls[$index] .= ' ON UPDATE ' . $fkey['onUpdate'];
            }
        }
        return $sqls;
    }
    /**
     * Resets table creation vars
     *
     * @return void
     */
    public function reset()
    {
        $this->fields = $this->keys = $this->unique_keys = $this->primary_keys = $this->foreign_keys = [];
    }
}