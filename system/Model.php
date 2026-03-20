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
namespace Code_Igniter;

use Closure;
use Code_Igniter\Database\Base_Builder;
use Code_Igniter\Database\Base_Connection;
use Code_Igniter\Database\Connection_Interface;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Database\Exceptions\Data_Exception;
use Code_Igniter\Entity\Entity;
use Code_Igniter\Exceptions\BadMethodCallException;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\Exceptions\Model_Exception;
use Code_Igniter\Validation\Validation_Interface;
use Config\Database;
use Config\Feature;
use stdClass;
/**
 * The Model class extends BaseModel and provides additional
 * convenient features that makes working with a SQL database
 * table less painful.
 *
 * It will:
 *      - automatically connect to database
 *      - allow intermingling calls to the builder
 *      - removes the need to use Result object directly in most cases
 *
 * @property-read BaseConnection $db
 *
 * @method $this groupBy($by, ?bool $escape = null)
 * @method $this groupEnd()
 * @method $this groupStart()
 * @method $this having($key, $value = null, ?bool $escape = null)
 * @method $this havingGroupEnd()
 * @method $this havingGroupStart()
 * @method $this havingIn(?string $key = null, $values = null, ?bool $escape = null)
 * @method $this havingLike($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this havingNotIn(?string $key = null, $values = null, ?bool $escape = null)
 * @method $this join(string $table, string $cond, string $type = '', ?bool $escape = null)
 * @method $this like($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this limit(?int $value = null, ?int $offset = 0)
 * @method $this notGroupStart()
 * @method $this notHavingGroupStart()
 * @method $this notHavingLike($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this notLike($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this offset(int $offset)
 * @method $this orderBy(string $orderBy, string $direction = '', ?bool $escape = null)
 * @method $this orGroupStart()
 * @method $this orHaving($key, $value = null, ?bool $escape = null)
 * @method $this orHavingGroupStart()
 * @method $this orHavingIn(?string $key = null, $values = null, ?bool $escape = null)
 * @method $this orHavingLike($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this orHavingNotIn(?string $key = null, $values = null, ?bool $escape = null)
 * @method $this orLike($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this orNotGroupStart()
 * @method $this orNotHavingGroupStart()
 * @method $this orNotHavingLike($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this orNotLike($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this orWhere($key, $value = null, ?bool $escape = null)
 * @method $this orWhereIn(?string $key = null, $values = null, ?bool $escape = null)
 * @method $this orWhereNotIn(?string $key = null, $values = null, ?bool $escape = null)
 * @method $this select($select = '*', ?bool $escape = null)
 * @method $this selectAvg(string $select = '', string $alias = '')
 * @method $this selectCount(string $select = '', string $alias = '')
 * @method $this selectMax(string $select = '', string $alias = '')
 * @method $this selectMin(string $select = '', string $alias = '')
 * @method $this selectSum(string $select = '', string $alias = '')
 * @method $this when($condition, callable $callback, ?callable $defaultCallback = null)
 * @method $this whenNot($condition, callable $callback, ?callable $defaultCallback = null)
 * @method $this where($key, $value = null, ?bool $escape = null)
 * @method $this whereIn(?string $key = null, $values = null, ?bool $escape = null)
 * @method $this whereNotIn(?string $key = null, $values = null, ?bool $escape = null)
 *
 * @phpstan-import-type row_array from BaseModel
 */
class Model extends Base_Model
{
    /**
     * Name of database table.
     *
     * @var string
     */
    protected $table;
    /**
     * The table's primary key.
     *
     * @var string
     */
    protected $primary_key = 'id';
    /**
     * Whether primary key uses auto increment.
     *
     * @var bool
     */
    protected $use_auto_increment = true;
    /**
     * Query Builder object.
     *
     * @var BaseBuilder|null
     */
    protected $builder;
    /**
     * Holds information passed in via 'set'
     * so that we can capture it (not the builder)
     * and ensure it gets validated first.
     *
     * @var array{escape: array<int|string, bool|null>, data: row_array}|array{}
     */
    protected $temp_data = [];
    /**
     * Escape array that maps usage of escape
     * flag for every parameter.
     *
     * @var array<int|string, bool|null>
     */
    protected $escape = [];
    /**
     * Builder method names that should not be used in the Model.
     *
     * @var list<string>
     */
    private array $builder_methods_not_available = ['getCompiledInsert', 'getCompiledSelect', 'getCompiledUpdate'];
    public function __construct(?Connection_Interface $db = null, ?Validation_Interface $validation = null)
    {
        /** @var BaseConnection $db */
        $db ??= Database::connect($this->db_group);
        $this->db = $db;
        parent::__construct($validation);
    }
    /**
     * Specify the table associated with a model.
     *
     * @return $this
     */
    public function set_table(string $table)
    {
        $this->table = $table;
        return $this;
    }
    protected function do_find(bool $singleton, $id = null)
    {
        $builder = $this->builder();
        $use_cast = $this->use_casts();
        if ($use_cast) {
            $return_type = $this->temp_return_type;
            $this->as_array();
        }
        if ($this->temp_use_soft_deletes) {
            $builder->where($this->table . '.' . $this->deleted_field, null);
        }
        $row = null;
        $rows = [];
        if (is_array($id)) {
            $rows = $builder->where_in($this->table . '.' . $this->primary_key, $id)->get()->get_result($this->temp_return_type);
        } elseif ($singleton) {
            $row = $builder->where($this->table . '.' . $this->primary_key, $id)->get()->get_first_row($this->temp_return_type);
        } else {
            $rows = $builder->get()->get_result($this->temp_return_type);
        }
        if ($use_cast) {
            $this->temp_return_type = $return_type;
            if ($singleton) {
                if ($row === null) {
                    return null;
                }
                return $this->convert_to_return_type($row, $return_type);
            }
            foreach ($rows as $i => $row) {
                $rows[$i] = $this->convert_to_return_type($row, $return_type);
            }
            return $rows;
        }
        if ($singleton) {
            return $row;
        }
        return $rows;
    }
    protected function do_find_column(string $column_name)
    {
        return $this->select($column_name)->as_array()->find();
    }
    /**
     * {@inheritDoc}
     *
     * Works with the current Query Builder instance.
     */
    protected function do_find_all(?int $limit = null, int $offset = 0)
    {
        $limit_zero_as_all = config(Feature::class)->limit_zero_as_all ?? true;
        if ($limit_zero_as_all) {
            $limit ??= 0;
        }
        $builder = $this->builder();
        $use_cast = $this->use_casts();
        if ($use_cast) {
            $return_type = $this->temp_return_type;
            $this->as_array();
        }
        if ($this->temp_use_soft_deletes) {
            $builder->where($this->table . '.' . $this->deleted_field, null);
        }
        $results = $builder->limit($limit, $offset)->get()->get_result($this->temp_return_type);
        if ($use_cast) {
            foreach ($results as $i => $row) {
                $results[$i] = $this->convert_to_return_type($row, $return_type);
            }
            $this->temp_return_type = $return_type;
        }
        return $results;
    }
    /**
     * {@inheritDoc}
     *
     * Will take any previous Query Builder calls into account
     * when determining the result set.
     */
    protected function do_first()
    {
        $builder = $this->builder();
        $use_cast = $this->use_casts();
        if ($use_cast) {
            $return_type = $this->temp_return_type;
            $this->as_array();
        }
        if ($this->temp_use_soft_deletes) {
            $builder->where($this->table . '.' . $this->deleted_field, null);
        } elseif ($this->use_soft_deletes && $builder->qb_group_by === [] && $this->primary_key !== '') {
            $builder->group_by($this->table . '.' . $this->primary_key);
        }
        // Some databases, like PostgreSQL, need order
        // information to consistently return correct results.
        if ($builder->qb_group_by !== [] && $builder->qb_order_by === [] && $this->primary_key !== '') {
            $builder->order_by($this->table . '.' . $this->primary_key, 'asc');
        }
        $row = $builder->limit(1, 0)->get()->get_first_row($this->temp_return_type);
        if ($use_cast && $row !== null) {
            $row = $this->convert_to_return_type($row, $return_type);
            $this->temp_return_type = $return_type;
        }
        return $row;
    }
    protected function do_insert(array $row)
    {
        $escape = $this->escape;
        $this->escape = [];
        // Require non-empty primaryKey when
        // not using auto-increment feature
        if (!$this->use_auto_increment) {
            if (!isset($row[$this->primary_key])) {
                throw Data_Exception::for_empty_primary_key('insert');
            }
            // Validate the primary key value (arrays not allowed for insert)
            $this->validate_id($row[$this->primary_key], false);
        }
        $builder = $this->builder();
        // Must use the set() method to ensure to set the correct escape flag
        foreach ($row as $key => $val) {
            $builder->set($key, $val, $escape[$key] ?? null);
        }
        if ($this->allow_empty_inserts && $row === []) {
            $table = $this->db->protect_identifiers($this->table, true, null, false);
            if ($this->db->get_platform() === 'MySQLi') {
                $sql = 'INSERT INTO ' . $table . ' VALUES ()';
            } elseif ($this->db->get_platform() === 'OCI8') {
                $all_fields = $this->db->protect_identifiers(array_map(static fn($row) => $row->name, $this->db->get_field_data($this->table)), false, true);
                $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(',', $all_fields), substr(str_repeat(',DEFAULT', count($all_fields)), 1));
            } else {
                $sql = 'INSERT INTO ' . $table . ' DEFAULT VALUES';
            }
            $result = $this->db->query($sql);
        } else {
            $result = $builder->insert();
        }
        // If insertion succeeded then save the insert ID
        if ($result) {
            $this->insert_id = $this->use_auto_increment ? $this->db->insert_id() : $row[$this->primary_key];
        }
        return $result;
    }
    protected function do_insert_batch(?array $set = null, ?bool $escape = null, int $batch_size = 100, bool $testing = false)
    {
        if (is_array($set) && !$this->use_auto_increment) {
            foreach ($set as $row) {
                // Require non-empty $primaryKey when
                // not using auto-increment feature
                if (!isset($row[$this->primary_key])) {
                    throw Data_Exception::for_empty_primary_key('insertBatch');
                }
                // Validate the primary key value
                $this->validate_id($row[$this->primary_key], false);
            }
        }
        return $this->builder()->test_mode($testing)->insert_batch($set, $escape, $batch_size);
    }
    protected function do_update($id = null, $row = null): bool
    {
        $escape = $this->escape;
        $this->escape = [];
        $builder = $this->builder();
        if (is_array($id) && $id !== []) {
            $builder = $builder->where_in($this->table . '.' . $this->primary_key, $id);
        }
        // Must use the set() method to ensure to set the correct escape flag
        foreach ($row as $key => $val) {
            $builder->set($key, $val, $escape[$key] ?? null);
        }
        if ($builder->get_compiled_qb_where() === []) {
            throw new Database_Exception('Updates are not allowed unless they contain a "where" or "like" clause.');
        }
        return $builder->update();
    }
    protected function do_update_batch(?array $set = null, ?string $index = null, int $batch_size = 100, bool $return_sql = false)
    {
        return $this->builder()->test_mode($return_sql)->update_batch($set, $index, $batch_size);
    }
    protected function do_delete($id = null, bool $purge = false)
    {
        $set = [];
        $builder = $this->builder();
        if (is_array($id) && $id !== []) {
            $builder = $builder->where_in($this->primary_key, $id);
        }
        if ($this->use_soft_deletes && !$purge) {
            if ($builder->get_compiled_qb_where() === []) {
                throw new Database_Exception('Deletes are not allowed unless they contain a "where" or "like" clause.');
            }
            $builder->where($this->deleted_field);
            $set[$this->deleted_field] = $this->set_date();
            if ($this->use_timestamps && $this->updated_field !== '') {
                $set[$this->updated_field] = $this->set_date();
            }
            return $builder->update($set);
        }
        return $builder->delete();
    }
    protected function do_purge_deleted()
    {
        return $this->builder()->where($this->table . '.' . $this->deleted_field . ' IS NOT NULL')->delete();
    }
    protected function do_only_deleted()
    {
        $this->builder()->where($this->table . '.' . $this->deleted_field . ' IS NOT NULL');
    }
    protected function do_replace(?array $row = null, bool $return_sql = false)
    {
        return $this->builder()->test_mode($return_sql)->replace($row);
    }
    /**
     * {@inheritDoc}
     *
     * The return array should be in the following format:
     *  `['source' => 'message']`.
     * This method works only with dbCalls.
     */
    protected function do_errors()
    {
        // $error is always ['code' => string|int, 'message' => string]
        $error = $this->db->error();
        if ((int) $error['code'] === 0) {
            return [];
        }
        return [$this->db::class => $error['message']];
    }
    public function get_id_value($row)
    {
        if (is_object($row)) {
            // Get the raw or mapped primary key value of the Entity.
            if ($row instanceof Entity && $row->{$this->primary_key} !== null) {
                $cast = $row->cast();
                // Disable Entity casting, because raw primary key value is needed for database.
                $row->cast(false);
                $primary_key = $row->{$this->primary_key};
                // Restore Entity casting setting.
                $row->cast($cast);
                return $primary_key;
            }
            if (!$row instanceof Entity && isset($row->{$this->primary_key})) {
                return $row->{$this->primary_key};
            }
        }
        if (is_array($row) && isset($row[$this->primary_key])) {
            return $row[$this->primary_key];
        }
        return null;
    }
    public function count_all_results(bool $reset = true, bool $test = false)
    {
        if ($this->temp_use_soft_deletes) {
            $this->builder()->where($this->table . '.' . $this->deleted_field, null);
        }
        // When $reset === false, the $tempUseSoftDeletes will be
        // dependent on $useSoftDeletes value because we don't
        // want to add the same "where" condition for the second time.
        $this->temp_use_soft_deletes = $reset ? $this->use_soft_deletes : ($this->use_soft_deletes ? false : $this->use_soft_deletes);
        return $this->builder()->test_mode($test)->count_all_results($reset);
    }
    /**
     * {@inheritDoc}
     *
     * Works with `$this->builder` to get the Compiled select to
     * determine the rows to operate on.
     * This method works only with dbCalls.
     */
    public function chunk(int $size, Closure $user_func)
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('chunk() requires a positive integer for the $size argument.');
        }
        $total = $this->builder()->count_all_results(false);
        $offset = 0;
        while ($offset < $total) {
            $builder = clone $this->builder();
            $rows = $builder->get($size, $offset);
            if (!$rows) {
                throw Data_Exception::for_empty_dataset('chunk');
            }
            $rows = $rows->get_result($this->temp_return_type);
            $offset += $size;
            if ($rows === []) {
                continue;
            }
            foreach ($rows as $row) {
                if ($user_func($row) === false) {
                    return;
                }
            }
        }
    }
    /**
     * Provides a shared instance of the Query Builder.
     *
     * @param non-empty-string|null $table
     *
     * @return BaseBuilder
     *
     * @throws ModelException
     */
    public function builder(?string $table = null)
    {
        // Check for an existing Builder
        if ($this->builder instanceof Base_Builder) {
            // Make sure the requested table matches the builder
            if ((string) $table !== '' && $this->builder->get_table() !== $table) {
                return $this->db->table($table);
            }
            return $this->builder;
        }
        // We're going to force a primary key to exist
        // so we don't have overly convoluted code,
        // and future features are likely to require them.
        if ($this->primary_key === '') {
            throw Model_Exception::for_no_primary_key(static::class);
        }
        $table = (string) $table === '' ? $this->table : $table;
        // Ensure we have a good db connection
        if (!$this->db instanceof Base_Connection) {
            $this->db = Database::connect($this->db_group);
        }
        $builder = $this->db->table($table);
        // Only consider it "shared" if the table is correct
        if ($table === $this->table) {
            $this->builder = $builder;
        }
        return $builder;
    }
    /**
     * Captures the builder's set() method so that we can validate the
     * data here. This allows it to be used with any of the other
     * builder methods and still get validated data, like replace.
     *
     * @param object|row_array|string           $key    Field name, or an array of field/value pairs, or an object
     * @param bool|float|int|object|string|null $value  Field value, if $key is a single field
     * @param bool|null                         $escape Whether to escape values
     *
     * @return $this
     */
    public function set($key, $value = '', ?bool $escape = null)
    {
        if (is_object($key)) {
            $key = $key instanceof stdClass ? (array) $key : $this->object_to_array($key);
        }
        $data = is_array($key) ? $key : [$key => $value];
        foreach (array_keys($data) as $k) {
            $this->temp_data['escape'][$k] = $escape;
        }
        $this->temp_data['data'] = array_merge($this->temp_data['data'] ?? [], $data);
        return $this;
    }
    protected function should_update($row): bool
    {
        if (parent::should_update($row) === false) {
            return false;
        }
        if ($this->use_auto_increment === true) {
            return true;
        }
        // When useAutoIncrement feature is disabled, check
        // in the database if given record already exists
        return $this->where($this->primary_key, $this->get_id_value($row))->count_all_results() === 1;
    }
    public function insert($row = null, bool $return_id = true)
    {
        if (isset($this->temp_data['data'])) {
            if ($row === null) {
                $row = $this->temp_data['data'];
            } else {
                $row = $this->transform_data_to_array($row, 'insert');
                $row = array_merge($this->temp_data['data'], $row);
            }
        }
        $this->escape = $this->temp_data['escape'] ?? [];
        $this->temp_data = [];
        return parent::insert($row, $return_id);
    }
    protected function do_protect_fields_for_insert(array $row): array
    {
        if (!$this->protect_fields) {
            return $row;
        }
        if ($this->allowed_fields === []) {
            throw Data_Exception::for_invalid_allowed_fields(static::class);
        }
        foreach (array_keys($row) as $key) {
            // Do not remove the non-auto-incrementing primary key data.
            if ($this->use_auto_increment === false && $key === $this->primary_key) {
                continue;
            }
            if (!in_array($key, $this->allowed_fields, true)) {
                unset($row[$key]);
            }
        }
        return $row;
    }
    public function update($id = null, $row = null): bool
    {
        if (isset($this->temp_data['data'])) {
            if ($row === null) {
                $row = $this->temp_data['data'];
            } else {
                $row = $this->transform_data_to_array($row, 'update');
                $row = array_merge($this->temp_data['data'], $row);
            }
        }
        $this->escape = $this->temp_data['escape'] ?? [];
        $this->temp_data = [];
        return parent::update($id, $row);
    }
    protected function object_to_raw_array($object, bool $only_changed = true, bool $recursive = false): array
    {
        return parent::object_to_raw_array($object, $only_changed);
    }
    /**
     * Provides/instantiates the builder/db connection and model's table/primary key names and return type.
     *
     * @return array<int|string, mixed>|BaseBuilder|bool|float|int|object|string|null
     */
    public function __get(string $name)
    {
        if (parent::__isset($name)) {
            return parent::__get($name);
        }
        return $this->builder()->{$name} ?? null;
    }
    /**
     * Checks for the existence of properties across this model, builder, and db connection.
     */
    public function __isset(string $name): bool
    {
        if (parent::__isset($name)) {
            return true;
        }
        return isset($this->builder()->{$name});
    }
    /**
     * Provides direct access to method in the builder (if available)
     * and the database connection.
     *
     * @return $this|array<int|string, mixed>|BaseBuilder|bool|float|int|object|string|null
     */
    public function __call(string $name, array $params)
    {
        $builder = $this->builder();
        $result = null;
        if (method_exists($this->db, $name)) {
            $result = $this->db->{$name}(...$params);
        } elseif (method_exists($builder, $name)) {
            $this->check_builder_method($name);
            $result = $builder->{$name}(...$params);
        } else {
            throw new BadMethodCallException('Call to undefined method ' . static::class . '::' . $name);
        }
        if ($result instanceof Base_Builder) {
            return $this;
        }
        return $result;
    }
    /**
     * Checks the Builder method name that should not be used in the Model.
     */
    private function check_builder_method(string $name): void
    {
        if (in_array($name, $this->builder_methods_not_available, true)) {
            throw Model_Exception::for_method_not_available(static::class, $name . '()');
        }
    }
}