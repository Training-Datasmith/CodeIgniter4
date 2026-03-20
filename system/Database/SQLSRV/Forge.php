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

use Code_Igniter\Database\Base_Connection;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Database\Forge as BaseForge;
use Throwable;
/**
 * Forge for SQLSRV
 */
class Forge extends Base_Forge
{
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
    protected $drop_index_str;
    /**
     * CREATE DATABASE IF statement
     *
     * @todo missing charset, collat & check for existent
     *
     * @var string
     */
    protected $create_database_if_str = "DECLARE @DBName VARCHAR(255) = '%s'\nDECLARE @SQL VARCHAR(max) = 'IF DB_ID( ''' + @DBName + ''' ) IS NULL CREATE DATABASE %s'\nEXEC( @SQL )";
    /**
     * CREATE DATABASE IF statement
     *
     * @todo missing charset & collat
     *
     * @var string
     */
    protected $create_database_str = 'CREATE DATABASE %s ';
    /**
     * CHECK DATABASE EXIST statement
     *
     * @var string
     */
    protected $check_database_exist_str = 'IF DB_ID( %s ) IS NOT NULL SELECT 1';
    /**
     * RENAME TABLE statement
     *
     * While the below statement would work, it returns an error.
     * Also MS recommends dropping and dropping and re-creating the table.
     *
     * @see https://docs.microsoft.com/en-us/sql/relational-databases/system-stored-procedures/sp-rename-transact-sql?view=sql-server-2017
     * 'EXEC sp_rename %s , %s ;'
     *
     * @var string
     */
    protected $rename_table_str;
    /**
     * UNSIGNED support
     *
     * @var array
     */
    protected $unsigned = ['TINYINT' => 'SMALLINT', 'SMALLINT' => 'INT', 'INT' => 'BIGINT', 'REAL' => 'FLOAT'];
    /**
     * Foreign Key Allowed Actions
     *
     * @var array
     */
    protected $fk_allow_actions = ['CASCADE', 'SET NULL', 'NO ACTION', 'RESTRICT', 'SET DEFAULT'];
    /**
     * CREATE TABLE IF statement
     *
     * @var string
     *
     * @deprecated This is no longer used.
     */
    protected $create_table_if_str;
    /**
     * CREATE TABLE statement
     *
     * @var string
     */
    protected $create_table_str;
    public function __construct(Base_Connection $db)
    {
        parent::__construct($db);
        $this->create_table_str = '%s ' . $this->db->escape_identifiers($this->db->schema) . ".%s (%s\n) ";
        $this->rename_table_str = 'EXEC sp_rename [' . $this->db->escape_identifiers($this->db->schema) . '.%s] , %s ;';
        $this->drop_constraint_str = 'ALTER TABLE ' . $this->db->escape_identifiers($this->db->schema) . '.%s DROP CONSTRAINT %s';
        $this->drop_index_str = 'DROP INDEX %s ON ' . $this->db->escape_identifiers($this->db->schema) . '.%s';
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
        if ($if_not_exists) {
            $sql = sprintf($this->create_database_if_str, $db_name, $this->db->escape_identifier($db_name));
        } else {
            $sql = sprintf($this->create_database_str, $this->db->escape_identifier($db_name));
        }
        try {
            if (!$this->db->query($sql)) {
                // @codeCoverageIgnoreStart
                if ($this->db->db_debug) {
                    throw new Database_Exception('Unable to create the specified database.');
                }
                return false;
                // @codeCoverageIgnoreEnd
            }
            if (isset($this->db->data_cache['db_names'])) {
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
     * {@inheritDoc}
     *
     * @see https://stackoverflow.com/questions/7469130/cannot-drop-database-because-it-is-currently-in-use
     */
    public function drop_database(string $db_name): bool
    {
        try {
            $this->db->query(sprintf('ALTER DATABASE %s SET SINGLE_USER WITH ROLLBACK IMMEDIATE', $this->db->escape_identifier($db_name)));
        } catch (Database_Exception) {
            // no-op
        }
        return parent::drop_database($db_name);
    }
    /**
     * CREATE TABLE attributes
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
        // Handle DROP here
        if ($alter_type === 'DROP') {
            $column_names_to_drop = $processed_fields;
            // check if fields are part of any indexes
            $index_data = $this->db->get_index_data($table);
            foreach ($index_data as $index) {
                if (is_string($column_names_to_drop)) {
                    $column_names_to_drop = explode(',', $column_names_to_drop);
                }
                $fld = array_intersect($column_names_to_drop, $index->fields);
                // Drop index if field is part of an index
                if ($fld !== []) {
                    $this->_drop_index($table, $index);
                }
            }
            $full_table = $this->db->escape_identifiers($this->db->schema) . '.' . $this->db->escape_identifiers($table);
            // Drop default constraints
            $fields = implode(',', $this->db->escape((array) $column_names_to_drop));
            $sql = <<<SQL
            SELECT name
            FROM sys.default_constraints
            WHERE parent_object_id = OBJECT_ID('{$full_table}')
            AND parent_column_id IN (
            SELECT column_id FROM sys.columns WHERE name IN ({$fields}) AND object_id = OBJECT_ID(N'{$full_table}')
            )
            SQL;
            foreach ($this->db->query($sql)->get_result_array() as $index) {
                $this->db->query('ALTER TABLE ' . $full_table . ' DROP CONSTRAINT ' . $index['name'] . '');
            }
            $sql = 'ALTER TABLE ' . $full_table . ' DROP ';
            $fields = array_map(static fn($item): string => 'COLUMN [' . trim($item) . ']', (array) $column_names_to_drop);
            return $sql . implode(',', $fields);
        }
        $sql = 'ALTER TABLE ' . $this->db->escape_identifiers($this->db->schema) . '.' . $this->db->escape_identifiers($table);
        $sql .= $alter_type === 'ADD' ? 'ADD ' : ' ';
        $sqls = [];
        if ($alter_type === 'ADD') {
            foreach ($processed_fields as $field) {
                $sqls[] = $sql . ($field['_literal'] !== false ? $field['_literal'] : $this->_process_column($field));
            }
            return $sqls;
        }
        foreach ($processed_fields as $field) {
            if ($field['_literal'] !== false) {
                return false;
            }
            if (isset($field['type'])) {
                $sqls[] = $sql . ' ALTER COLUMN ' . $this->db->escape_identifiers($field['name']) . " {$field['type']}{$field['length']}";
            }
            if (!empty($field['default'])) {
                $full_table = $this->db->escape_identifiers($this->db->schema) . '.' . $this->db->escape_identifiers($table);
                $col_name = $field['name'];
                // bare, for sys.columns lookup
                // find the existing default constraint name for this column
                $find_sql = <<<SQL
                SELECT dc.name AS constraint_name
                FROM sys.default_constraints dc
                JOIN sys.columns c
                    ON dc.parent_object_id = c.object_id
                    AND dc.parent_column_id = c.column_id
                WHERE dc.parent_object_id = OBJECT_ID(N'{$full_table}')
                    AND c.name = N'{$col_name}';
                SQL;
                $to_drop = $this->db->query($find_sql)->get_row_array();
                if (isset($to_drop['constraint_name']) && $to_drop['constraint_name'] !== '') {
                    $sqls[] = $sql . ' DROP CONSTRAINT ' . $this->db->escape_identifiers($to_drop['constraint_name']);
                }
                $sqls[] = $sql . ' ADD CONSTRAINT ' . $this->db->escape_identifiers($field['name'] . '_def') . "{$field['default']} FOR " . $this->db->escape_identifiers($field['name']);
            }
            $nullable = true;
            // Nullable by default.
            if (isset($field['null']) && ($field['null'] === false || $field['null'] === ' NOT ' . $this->null)) {
                $nullable = false;
            }
            $sqls[] = $sql . ' ALTER COLUMN ' . $this->db->escape_identifiers($field['name']) . " {$field['type']}{$field['length']} " . ($nullable ? '' : 'NOT') . ' NULL';
            if (!empty($field['comment'])) {
                $sqls[] = 'EXEC sys.sp_addextendedproperty ' . "@name=N'Caption', @value=N'" . $field['comment'] . "' , " . "@level0type=N'SCHEMA',@level0name=N'" . $this->db->schema . "', " . "@level1type=N'TABLE',@level1name=N'" . $this->db->escape_identifiers($table) . "', " . "@level2type=N'COLUMN',@level2name=N'" . $this->db->escape_identifiers($field['name']) . "'";
            }
            if (!empty($field['new_name'])) {
                $sqls[] = "EXEC sp_rename  '[" . $this->db->schema . '].[' . $table . '].[' . $field['name'] . "]' , '" . $field['new_name'] . "', 'COLUMN';";
            }
        }
        return $sqls;
    }
    /**
     * Drop index for table
     *
     * @return mixed
     */
    protected function _drop_index(string $table, object $index_data)
    {
        if ($index_data->type === 'PRIMARY') {
            $sql = 'ALTER TABLE [' . $this->db->schema . '].[' . $table . '] DROP [' . $index_data->name . ']';
        } else {
            $sql = 'DROP INDEX [' . $index_data->name . '] ON [' . $this->db->schema . '].[' . $table . ']';
        }
        return $this->db->simple_query($sql);
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
                $sqls[] = 'ALTER TABLE ' . $this->db->escape_identifiers($this->db->schema) . '.' . $this->db->escape_identifiers($table) . ' ADD CONSTRAINT ' . $key_name . ' UNIQUE (' . implode(', ', $this->db->escape_identifiers($this->keys[$i]['fields'])) . ');';
                continue;
            }
            $sqls[] = 'CREATE INDEX ' . $key_name . ' ON ' . $this->db->escape_identifiers($this->db->schema) . '.' . $this->db->escape_identifiers($table) . ' (' . implode(', ', $this->db->escape_identifiers($this->keys[$i]['fields'])) . ');';
        }
        return $sqls;
    }
    /**
     * Process column
     */
    protected function _process_column(array $processed_field): string
    {
        return $this->db->escape_identifiers($processed_field['name']) . (empty($processed_field['new_name']) ? '' : ' ' . $this->db->escape_identifiers($processed_field['new_name'])) . ' ' . $processed_field['type'] . ($processed_field['type'] === 'text' ? '' : $processed_field['length']) . $processed_field['default'] . $processed_field['null'] . $processed_field['auto_increment'] . '' . $processed_field['unique'];
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
            case 'MEDIUMINT':
                $attributes['TYPE'] = 'INTEGER';
                $attributes['UNSIGNED'] = false;
                break;
            case 'INTEGER':
                $attributes['TYPE'] = 'INT';
                break;
            case 'ENUM':
                // in char(n) and varchar(n), the n defines the string length in
                // bytes (0 to 8,000).
                // https://learn.microsoft.com/en-us/sql/t-sql/data-types/char-and-varchar-transact-sql?view=sql-server-ver16#remarks
                $max_length = max(array_map(strlen(...), $attributes['CONSTRAINT']));
                $attributes['TYPE'] = 'VARCHAR';
                $attributes['CONSTRAINT'] = $max_length;
                break;
            case 'TIMESTAMP':
                $attributes['TYPE'] = 'DATETIME';
                break;
            case 'BOOLEAN':
                $attributes['TYPE'] = 'BIT';
                break;
            case 'BLOB':
                $attributes['TYPE'] = 'VARBINARY';
                $attributes['CONSTRAINT'] ??= 'MAX';
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
        if (!empty($attributes['AUTO_INCREMENT']) && $attributes['AUTO_INCREMENT'] === true && str_contains(strtolower($field['type']), strtolower('INT'))) {
            $field['auto_increment'] = ' IDENTITY(1,1)';
        }
    }
    /**
     * Generates a platform-specific DROP TABLE string
     *
     * @todo Support for cascade
     */
    protected function _drop_table(string $table, bool $if_exists, bool $cascade): string
    {
        $sql = 'DROP TABLE';
        if ($if_exists) {
            $sql .= ' IF EXISTS ';
        }
        $table = ' [' . $this->db->database . '].[' . $this->db->schema . '].[' . $table . '] ';
        $sql .= $table;
        if ($cascade) {
            $sql .= '';
        }
        return $sql;
    }
    /**
     * Constructs sql to check if key is a constraint.
     */
    protected function _drop_key_as_constraint(string $table, string $constraint_name): string
    {
        return "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS\n                WHERE TABLE_NAME= '" . trim($table, '"') . "'\n                AND CONSTRAINT_NAME = '" . trim($constraint_name, '"') . "'";
    }
}