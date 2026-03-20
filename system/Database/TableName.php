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

/**
 * Represents a table name in SQL.
 *
 * @interal
 *
 * @see \CodeIgniter\Database\TableNameTest
 */
class Table_Name
{
    /**
     * @param string $actualTable  Actual table name
     * @param string $logicalTable Logical table name (w/o DB prefix)
     * @param string $schema       Schema name
     * @param string $database     Database name
     * @param string $alias        Alias name
     */
    protected function __construct(private readonly string $actual_table, private readonly string $logical_table = '', private readonly string $schema = '', private readonly string $database = '', private readonly string $alias = '')
    {
    }
    /**
     * Creates a new instance.
     *
     * @param string $table Table name (w/o DB prefix)
     * @param string $alias Alias name
     */
    public static function create(string $db_prefix, string $table, string $alias = ''): self
    {
        return new self($db_prefix . $table, $table, '', '', $alias);
    }
    /**
     * Creates a new instance from an actual table name.
     *
     * @param string $actualTable Actual table name with DB prefix
     * @param string $alias       Alias name
     */
    public static function from_actual_name(string $db_prefix, string $actual_table, string $alias = ''): self
    {
        $prefix = $db_prefix;
        $logical_table = '';
        if (str_starts_with($actual_table, $prefix)) {
            $logical_table = substr($actual_table, strlen($prefix));
        }
        return new self($actual_table, $logical_table, '', $alias);
    }
    /**
     * Creates a new instance from full name.
     *
     * @param string $table    Table name (w/o DB prefix)
     * @param string $schema   Schema name
     * @param string $database Database name
     * @param string $alias    Alias name
     */
    public static function from_full_name(string $db_prefix, string $table, string $schema = '', string $database = '', string $alias = ''): self
    {
        return new self($db_prefix . $table, $table, $schema, $database, $alias);
    }
    /**
     * Returns the single segment table name w/o DB prefix.
     */
    public function get_table_name(): string
    {
        return $this->logical_table;
    }
    /**
     * Returns the actual single segment table name w/z DB prefix.
     */
    public function get_actual_table_name(): string
    {
        return $this->actual_table;
    }
    public function get_alias(): string
    {
        return $this->alias;
    }
    public function get_schema(): string
    {
        return $this->schema;
    }
    public function get_database(): string
    {
        return $this->database;
    }
}