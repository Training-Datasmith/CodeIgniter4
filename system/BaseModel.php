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
use Code_Igniter\Database\Base_Connection;
use Code_Igniter\Database\Base_Result;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Database\Exceptions\Data_Exception;
use Code_Igniter\Database\Query;
use Code_Igniter\Database\Raw_Sql;
use Code_Igniter\Data_Caster\Cast\Cast_Interface;
use Code_Igniter\Data_Converter\Data_Converter;
use Code_Igniter\Entity\Cast\Cast_Interface as EntityCastInterface;
use Code_Igniter\Entity\Entity;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\Exceptions\Model_Exception;
use Code_Igniter\I18n\Time;
use Code_Igniter\Pager\Pager;
use Code_Igniter\Validation\Validation_Interface;
use Config\Feature;
use ReflectionClass;
use Reflection_Exception;
use ReflectionProperty;
use stdClass;
/**
 * The BaseModel class provides a number of convenient features that
 * makes working with a databases less painful. Extending this class
 * provide means of implementing various database systems.
 *
 * It will:
 *      - simplifies pagination
 *      - allow specifying the return type (array, object, etc) with each call
 *      - automatically set and update timestamps
 *      - handle soft deletes
 *      - ensure validation is run against objects when saving items
 *      - process various callbacks
 *      - allow intermingling calls to the db connection
 *
 * @phpstan-type row_array               array<int|string, float|int|null|object|string|bool>
 * @phpstan-type event_data_beforeinsert array{data: row_array}
 * @phpstan-type event_data_afterinsert  array{id: int|string, data: row_array, result: bool}
 * @phpstan-type event_data_beforefind   array{id?: int|string, method: string, singleton: bool, limit?: int, offset?: int}
 * @phpstan-type event_data_afterfind    array{id: int|string|null|list<int|string>, data: row_array|list<row_array>|object|null, method: string, singleton: bool}
 * @phpstan-type event_data_beforeupdate array{id: null|list<int|string>, data: row_array}
 * @phpstan-type event_data_afterupdate  array{id: null|list<int|string>, data: row_array|object, result: bool}
 * @phpstan-type event_data_beforedelete array{id: null|list<int|string>, purge: bool}
 * @phpstan-type event_data_afterdelete  array{id: null|list<int|string>, data: null, purge: bool, result: bool}
 */
abstract class Base_Model
{
    /**
     * Pager instance.
     *
     * Populated after calling `$this->paginate()`.
     *
     * @var Pager
     */
    public $pager;
    /**
     * Database Connection.
     *
     * @var BaseConnection
     */
    protected $db;
    /**
     * Last insert ID.
     *
     * @var int|string
     */
    protected $insert_id = 0;
    /**
     * The Database connection group that
     * should be instantiated.
     *
     * @var non-empty-string|null
     */
    protected $db_group;
    /**
     * The format that the results should be returned as.
     *
     * Will be overridden if the `$this->asArray()`, `$this->asObject()` methods are used.
     *
     * @var 'array'|'object'|class-string
     */
    protected $return_type = 'array';
    /**
     * The temporary format of the result.
     *
     * Used by `$this->asArray()` and `$this->asObject()` to provide
     * temporary overrides of model default.
     *
     * @var 'array'|'object'|class-string
     */
    protected $temp_return_type;
    /**
     * Array of column names and the type of value to cast.
     *
     * @var array<string, string> Array order `['column' => 'type']`.
     */
    protected array $casts = [];
    /**
     * Custom convert handlers.
     *
     * @var array<string, class-string<CastInterface|EntityCastInterface>> Array order `['type' => 'classname']`.
     */
    protected array $cast_handlers = [];
    protected ?Data_Converter $converter = null;
    /**
     * Determines whether the model should protect field names during
     * mass assignment operations such as $this->insert(), $this->update().
     *
     * When set to `true`, only the fields explicitly defined in the `$allowedFields`
     * property will be allowed for mass assignment. This helps prevent
     * unintended modification of database fields and improves security
     * by avoiding mass assignment vulnerabilities.
     *
     * @var bool
     */
    protected $protect_fields = true;
    /**
     * An array of field names that are allowed
     * to be set by the user in inserts/updates.
     *
     * @var list<string>
     */
    protected $allowed_fields = [];
    /**
     * If true, will set created_at, and updated_at
     * values during insert and update routines.
     *
     * @var bool
     */
    protected $use_timestamps = false;
    /**
     * The type of column that created_at and updated_at
     * are expected to.
     *
     * @var 'date'|'datetime'|'int'
     */
    protected $date_format = 'datetime';
    /**
     * The column used for insert timestamps.
     *
     * @var string
     */
    protected $created_field = 'created_at';
    /**
     * The column used for update timestamps.
     *
     * @var string
     */
    protected $updated_field = 'updated_at';
    /**
     * If this model should use "softDeletes" and
     * simply set a date when rows are deleted, or
     * do hard deletes.
     *
     * @var bool
     */
    protected $use_soft_deletes = false;
    /**
     * Used by $this->withDeleted() to override the
     * model's "softDelete" setting.
     *
     * @var bool
     */
    protected $temp_use_soft_deletes;
    /**
     * The column used to save soft delete state.
     *
     * @var string
     */
    protected $deleted_field = 'deleted_at';
    /**
     * Whether to allow inserting empty data.
     */
    protected bool $allow_empty_inserts = false;
    /**
     * Whether to update Entity's only changed data.
     */
    protected bool $update_only_changed = true;
    /**
     * Rules used to validate data in insert(), update(), save(),
     * insertBatch(), and updateBatch() methods.
     *
     * The array must match the format of data passed to the `Validation`
     * library.
     *
     * @see https://codeigniter4.github.io/userguide/models/model.html#setting-validation-rules
     *
     * @var array<string, array<string, array<string, string>|string>|string>|string
     */
    protected $validation_rules = [];
    /**
     * Contains any custom error messages to be
     * used during data validation.
     *
     * @var array<string, array<string, string>> The column is used as the keys.
     */
    protected $validation_messages = [];
    /**
     * Skip the model's validation.
     *
     * Used in conjunction with `$this->skipValidation()`
     * to skip data validation for any future calls.
     *
     * @var bool
     */
    protected $skip_validation = false;
    /**
     * Whether rules should be removed that do not exist
     * in the passed data. Used in updates.
     *
     * @var bool
     */
    protected $clean_validation_rules = true;
    /**
     * Our validator instance.
     *
     * @var ValidationInterface|null
     */
    protected $validation;
    /*
     * Callbacks.
     *
     * Each array should contain the method names (within the model)
     * that should be called when those events are triggered.
     *
     * "Update" and "delete" methods are passed the same items that
     * are given to their respective method.
     *
     * "Find" methods receive the ID searched for (if present), and
     * 'afterFind' additionally receives the results that were found.
     */
    /**
     * Whether to trigger the defined callbacks.
     *
     * @var bool
     */
    protected $allow_callbacks = true;
    /**
     * Used by $this->allowCallbacks() to override the
     * model's $allowCallbacks setting.
     *
     * @var bool
     */
    protected $temp_allow_callbacks;
    /**
     * Callbacks for "beforeInsert" event.
     *
     * @var list<string>
     */
    protected $before_insert = [];
    /**
     * Callbacks for "afterInsert" event.
     *
     * @var list<string>
     */
    protected $after_insert = [];
    /**
     * Callbacks for "beforeUpdate" event.
     *
     * @var list<string>
     */
    protected $before_update = [];
    /**
     * Callbacks for "afterUpdate" event.
     *
     * @var list<string>
     */
    protected $after_update = [];
    /**
     * Callbacks for "beforeInsertBatch" event.
     *
     * @var list<string>
     */
    protected $before_insert_batch = [];
    /**
     * Callbacks for "afterInsertBatch" event.
     *
     * @var list<string>
     */
    protected $after_insert_batch = [];
    /**
     * Callbacks for "beforeUpdateBatch" event.
     *
     * @var list<string>
     */
    protected $before_update_batch = [];
    /**
     * Callbacks for "afterUpdateBatch" event.
     *
     * @var list<string>
     */
    protected $after_update_batch = [];
    /**
     * Callbacks for "beforeFind" event.
     *
     * @var list<string>
     */
    protected $before_find = [];
    /**
     * Callbacks for "afterFind" event.
     *
     * @var list<string>
     */
    protected $after_find = [];
    /**
     * Callbacks for "beforeDelete" event.
     *
     * @var list<string>
     */
    protected $before_delete = [];
    /**
     * Callbacks for "afterDelete" event.
     *
     * @var list<string>
     */
    protected $after_delete = [];
    public function __construct(?Validation_Interface $validation = null)
    {
        $this->temp_return_type = $this->return_type;
        $this->temp_use_soft_deletes = $this->use_soft_deletes;
        $this->temp_allow_callbacks = $this->allow_callbacks;
        $this->validation = $validation;
        $this->initialize();
        $this->create_data_converter();
    }
    /**
     * Creates DataConverter instance.
     */
    protected function create_data_converter(): void
    {
        if ($this->use_casts()) {
            $this->converter = new Data_Converter($this->casts, $this->cast_handlers, $this->db);
        }
    }
    /**
     * Are casts used?
     */
    protected function use_casts(): bool
    {
        return $this->casts !== [];
    }
    /**
     * Initializes the instance with any additional steps.
     * Optionally implemented by child classes.
     *
     * @return void
     */
    protected function initialize()
    {
    }
    /**
     * Fetches the row(s) of database with a primary key
     * matching $id.
     * This method works only with DB calls.
     *
     * @param bool                             $singleton Single or multiple results.
     * @param int|list<int|string>|string|null $id        One primary key or an array of primary keys.
     *
     * @return ($singleton is true ? object|row_array|null : list<object|row_array>) The resulting row of data or `null`.
     */
    abstract protected function do_find(bool $singleton, $id = null);
    /**
     * Fetches the column of database.
     * This method works only with DB calls.
     *
     * @return list<row_array>|null The resulting row of data or `null` if no data found.
     *
     * @throws DataException
     */
    abstract protected function do_find_column(string $column_name);
    /**
     * Fetches all results, while optionally limiting them.
     * This method works only with DB calls.
     *
     * @return list<object|row_array>
     */
    abstract protected function do_find_all(?int $limit = null, int $offset = 0);
    /**
     * Returns the first row of the result set.
     * This method works only with DB calls.
     *
     * @return object|row_array|null
     */
    abstract protected function do_first();
    /**
     * Inserts data into the current database.
     * This method works only with DB calls.
     *
     * @param row_array $row
     *
     * @return bool
     */
    abstract protected function do_insert(array $row);
    /**
     * Compiles batch insert and runs the queries, validating each row prior.
     * This method works only with DB calls.
     *
     * @param list<object|row_array>|null $set       An associative array of insert values.
     * @param bool|null                   $escape    Whether to escape values.
     * @param int                         $batchSize The size of the batch to run.
     * @param bool                        $testing   `true` means only number of records is returned, `false` will execute the query.
     *
     * @return false|int|list<string> Number of rows affected or `false` on failure, SQL array when test mode
     */
    abstract protected function do_insert_batch(?array $set = null, ?bool $escape = null, int $batch_size = 100, bool $testing = false);
    /**
     * Updates a single record in the database.
     * This method works only with DB calls.
     *
     * @param int|list<int|string>|string|null $id
     * @param row_array|null                   $row
     */
    abstract protected function do_update($id = null, $row = null): bool;
    /**
     * Compiles an update and runs the query.
     * This method works only with DB calls.
     *
     * @param list<object|row_array>|null $set       An associative array of update values.
     * @param string|null                 $index     The where key.
     * @param int                         $batchSize The size of the batch to run.
     * @param bool                        $returnSQL `true` means SQL is returned, `false` will execute the query.
     *
     * @return false|int|list<string> Number of rows affected or `false` on failure, SQL array when test mode
     *
     * @throws DatabaseException
     */
    abstract protected function do_update_batch(?array $set = null, ?string $index = null, int $batch_size = 100, bool $return_sql = false);
    /**
     * Deletes a single record from the database where $id matches
     * the table's primary key.
     * This method works only with DB calls.
     *
     * @param int|list<int|string>|string|null $id    The rows primary key(s).
     * @param bool                             $purge Allows overriding the soft deletes setting.
     *
     * @return bool|string Returns a SQL string if in test mode.
     *
     * @throws DatabaseException
     */
    abstract protected function do_delete($id = null, bool $purge = false);
    /**
     * Permanently deletes all rows that have been marked as deleted
     * through soft deletes (value of column $deletedField is not null).
     * This method works only with DB calls.
     *
     * @return bool|string Returns a SQL string if in test mode.
     */
    abstract protected function do_purge_deleted();
    /**
     * Works with the $this->find* methods to return only the rows that
     * have been deleted (value of column $deletedField is not null).
     * This method works only with DB calls.
     *
     * @return void
     */
    abstract protected function do_only_deleted();
    /**
     * Compiles a replace and runs the query.
     * This method works only with DB calls.
     *
     * @param row_array|null $row
     * @param bool           $returnSQL `true` means SQL is returned, `false` will execute the query.
     *
     * @return BaseResult|false|Query|string
     */
    abstract protected function do_replace(?array $row = null, bool $return_sql = false);
    /**
     * Grabs the last error(s) that occurred from the Database connection.
     * This method works only with DB calls.
     *
     * @return array<string, string>
     */
    abstract protected function do_errors();
    /**
     * Public getter to return the ID value for the data array or object.
     * For example with SQL this will return `$data->{$this->primaryKey}`.
     *
     * @param object|row_array $row
     *
     * @return int|string|null
     */
    abstract public function get_id_value($row);
    /**
     * Override countAllResults to account for soft deleted accounts.
     * This method works only with DB calls.
     *
     * @param bool $reset When `false`, the `$tempUseSoftDeletes` will be
     *                    dependent on `$useSoftDeletes` value because we don't
     *                    want to add the same "where" condition for the second time.
     * @param bool $test  `true` returns the number of all records, `false` will execute the query.
     *
     * @return int|string Returns a SQL string if in test mode.
     */
    abstract public function count_all_results(bool $reset = true, bool $test = false);
    /**
     * Loops over records in batches, allowing you to operate on them.
     * This method works only with DB calls.
     *
     * @param Closure(array<string, string>|object): mixed $userFunc
     *
     * @return void
     *
     * @throws DataException
     * @throws InvalidArgumentException if $size is not a positive integer
     */
    abstract public function chunk(int $size, Closure $user_func);
    /**
     * Fetches the row of database.
     *
     * @param int|list<int|string>|string|null $id One primary key or an array of primary keys.
     *
     * @return ($id is int|string ? object|row_array|null :  list<object|row_array>)
     */
    public function find($id = null)
    {
        $singleton = is_numeric($id) || is_string($id);
        if ($this->temp_allow_callbacks) {
            // Call the before event and check for a return
            $event_data = $this->trigger('beforeFind', ['id' => $id, 'method' => 'find', 'singleton' => $singleton]);
            if (isset($event_data['returnData']) && $event_data['returnData'] === true) {
                return $event_data['data'];
            }
        }
        $event_data = ['id' => $id, 'data' => $this->do_find($singleton, $id), 'method' => 'find', 'singleton' => $singleton];
        if ($this->temp_allow_callbacks) {
            $event_data = $this->trigger('afterFind', $event_data);
        }
        $this->temp_return_type = $this->return_type;
        $this->temp_use_soft_deletes = $this->use_soft_deletes;
        $this->temp_allow_callbacks = $this->allow_callbacks;
        return $event_data['data'];
    }
    /**
     * Fetches the column of database.
     *
     * @return list<bool|float|int|list<mixed>|object|string|null>|null The resulting row of data, or `null` if no data found.
     *
     * @throws DataException
     */
    public function find_column(string $column_name)
    {
        if (str_contains($column_name, ',')) {
            throw Data_Exception::for_find_column_have_multiple_columns();
        }
        $result_set = $this->do_find_column($column_name);
        return $result_set !== null ? array_column($result_set, $column_name) : null;
    }
    /**
     * Fetches all results, while optionally limiting them.
     *
     * @return list<object|row_array>
     */
    public function find_all(?int $limit = null, int $offset = 0)
    {
        $limit_zero_as_all = config(Feature::class)->limit_zero_as_all ?? true;
        if ($limit_zero_as_all) {
            $limit ??= 0;
        }
        if ($this->temp_allow_callbacks) {
            // Call the before event and check for a return
            $event_data = $this->trigger('beforeFind', ['method' => 'findAll', 'limit' => $limit, 'offset' => $offset, 'singleton' => false]);
            if (isset($event_data['returnData']) && $event_data['returnData'] === true) {
                return $event_data['data'];
            }
        }
        $event_data = ['data' => $this->do_find_all($limit, $offset), 'limit' => $limit, 'offset' => $offset, 'method' => 'findAll', 'singleton' => false];
        if ($this->temp_allow_callbacks) {
            $event_data = $this->trigger('afterFind', $event_data);
        }
        $this->temp_return_type = $this->return_type;
        $this->temp_use_soft_deletes = $this->use_soft_deletes;
        $this->temp_allow_callbacks = $this->allow_callbacks;
        return $event_data['data'];
    }
    /**
     * Returns the first row of the result set.
     *
     * @return object|row_array|null
     */
    public function first()
    {
        if ($this->temp_allow_callbacks) {
            // Call the before event and check for a return
            $event_data = $this->trigger('beforeFind', ['method' => 'first', 'singleton' => true]);
            if (isset($event_data['returnData']) && $event_data['returnData'] === true) {
                return $event_data['data'];
            }
        }
        $event_data = ['data' => $this->do_first(), 'method' => 'first', 'singleton' => true];
        if ($this->temp_allow_callbacks) {
            $event_data = $this->trigger('afterFind', $event_data);
        }
        $this->temp_return_type = $this->return_type;
        $this->temp_use_soft_deletes = $this->use_soft_deletes;
        $this->temp_allow_callbacks = $this->allow_callbacks;
        return $event_data['data'];
    }
    /**
     * A convenience method that will attempt to determine whether the
     * data should be inserted or updated.
     *
     * Will work with either an array or object.
     * When using with custom class objects,
     * you must ensure that the class will provide access to the class
     * variables, even if through a magic method.
     *
     * @param object|row_array $row
     *
     * @throws ReflectionException
     */
    public function save($row): bool
    {
        if ((array) $row === []) {
            return true;
        }
        if ($this->should_update($row)) {
            $response = $this->update($this->get_id_value($row), $row);
        } else {
            $response = $this->insert($row, false);
            if ($response !== false) {
                $response = true;
            }
        }
        return $response;
    }
    /**
     * This method is called on save to determine if entry have to be updated.
     * If this method returns `false` insert operation will be executed.
     *
     * @param object|row_array $row
     */
    protected function should_update($row): bool
    {
        $id = $this->get_id_value($row);
        return !in_array($id, [null, [], ''], true);
    }
    /**
     * Returns last insert ID or 0.
     *
     * @return int|string
     */
    public function get_insert_id()
    {
        return is_numeric($this->insert_id) ? (int) $this->insert_id : $this->insert_id;
    }
    /**
     * Validates that the primary key values are valid for update/delete/insert operations.
     * Throws exception if invalid.
     *
     * @param bool $allowArray Whether to allow array of IDs (true for update/delete, false for insert)
     *
     * @phpstan-assert non-zero-int|non-empty-list<int|string>|RawSql|non-falsy-string $id
     * @throws         InvalidArgumentException
     */
    protected function validate_id(mixed $id, bool $allow_array = true): void
    {
        if (is_array($id)) {
            // Check if arrays are allowed
            if (!$allow_array) {
                throw new InvalidArgumentException('Invalid primary key: only a single value is allowed, not an array.');
            }
            // Check for empty array
            if ($id === []) {
                throw new InvalidArgumentException('Invalid primary key: cannot be an empty array.');
            }
            // Validate each ID in the array recursively
            foreach ($id as $key => $value_id) {
                if (is_array($value_id)) {
                    throw new InvalidArgumentException(sprintf('Invalid primary key at index %s: nested arrays are not allowed.', $key));
                }
                // Recursive call for each value (single values only in recursion)
                $this->validate_id($value_id, false);
            }
            return;
        }
        // Allow RawSql objects for complex scenarios
        if ($id instanceof Raw_Sql) {
            return;
        }
        // Check for invalid single values
        if (in_array($id, [null, 0, '0', '', true, false], true)) {
            $type = is_bool($id) ? 'boolean ' . var_export($id, true) : var_export($id, true);
            throw new InvalidArgumentException(sprintf('Invalid primary key: %s is not allowed.', $type));
        }
        // Only allow int and string at this point
        if (!is_int($id) && !is_string($id)) {
            throw new InvalidArgumentException(sprintf('Invalid primary key: must be int or string, %s given.', get_debug_type($id)));
        }
    }
    /**
     * Inserts data into the database. If an object is provided,
     * it will attempt to convert it to an array.
     *
     * @param object|row_array|null $row
     * @param bool                  $returnID Whether insert ID should be returned or not.
     *
     * @return ($returnID is true ? false|int|string : bool)
     *
     * @throws ReflectionException
     */
    public function insert($row = null, bool $return_id = true)
    {
        $this->insert_id = 0;
        // Set $cleanValidationRules to false temporary.
        $clean_validation_rules = $this->clean_validation_rules;
        $this->clean_validation_rules = false;
        $row = $this->transform_data_to_array($row, 'insert');
        // Validate data before saving.
        if (!$this->skip_validation && !$this->validate($row)) {
            // Restore $cleanValidationRules
            $this->clean_validation_rules = $clean_validation_rules;
            return false;
        }
        // Restore $cleanValidationRules
        $this->clean_validation_rules = $clean_validation_rules;
        // Must be called first, so we don't
        // strip out created_at values.
        $row = $this->do_protect_fields_for_insert($row);
        // doProtectFields() can further remove elements from
        // $row, so we need to check for empty dataset again
        if (!$this->allow_empty_inserts && $row === []) {
            throw Data_Exception::for_empty_dataset('insert');
        }
        // Set created_at and updated_at with same time
        $date = $this->set_date();
        $row = $this->set_created_field($row, $date);
        $row = $this->set_updated_field($row, $date);
        $event_data = ['data' => $row];
        if ($this->temp_allow_callbacks) {
            $event_data = $this->trigger('beforeInsert', $event_data);
        }
        $result = $this->do_insert($event_data['data']);
        $event_data = ['id' => $this->insert_id, 'data' => $event_data['data'], 'result' => $result];
        if ($this->temp_allow_callbacks) {
            // Trigger afterInsert events with the inserted data and new ID
            $this->trigger('afterInsert', $event_data);
        }
        $this->temp_allow_callbacks = $this->allow_callbacks;
        // If insertion failed, get out of here
        if (!$result) {
            return $result;
        }
        // otherwise return the insertID, if requested.
        return $return_id ? $this->insert_id : $result;
    }
    /**
     * Set datetime to created field.
     *
     * @param row_array  $row
     * @param int|string $date Timestamp or datetime string.
     *
     * @return row_array
     */
    protected function set_created_field(array $row, $date): array
    {
        if ($this->use_timestamps && $this->created_field !== '' && !array_key_exists($this->created_field, $row)) {
            $row[$this->created_field] = $date;
        }
        return $row;
    }
    /**
     * Set datetime to updated field.
     *
     * @param row_array  $row
     * @param int|string $date Timestamp or datetime string
     *
     * @return row_array
     */
    protected function set_updated_field(array $row, $date): array
    {
        if ($this->use_timestamps && $this->updated_field !== '' && !array_key_exists($this->updated_field, $row)) {
            $row[$this->updated_field] = $date;
        }
        return $row;
    }
    /**
     * Compiles batch insert runs the queries, validating each row prior.
     *
     * @param list<object|row_array>|null $set       An associative array of insert values.
     * @param bool|null                   $escape    Whether to escape values.
     * @param int                         $batchSize The size of the batch to run.
     * @param bool                        $testing   `true` means only number of records is returned, `false` will execute the query.
     *
     * @return false|int|list<string> Number of rows inserted or `false` on failure.
     *
     * @throws ReflectionException
     */
    public function insert_batch(?array $set = null, ?bool $escape = null, int $batch_size = 100, bool $testing = false)
    {
        // Set $cleanValidationRules to false temporary.
        $clean_validation_rules = $this->clean_validation_rules;
        $this->clean_validation_rules = false;
        if (is_array($set)) {
            foreach ($set as &$row) {
                $row = $this->transform_data_to_array($row, 'insert');
                // Validate every row.
                if (!$this->skip_validation && !$this->validate($row)) {
                    // Restore $cleanValidationRules
                    $this->clean_validation_rules = $clean_validation_rules;
                    return false;
                }
                // Must be called first so we don't
                // strip out created_at values.
                $row = $this->do_protect_fields_for_insert($row);
                // Set created_at and updated_at with same time
                $date = $this->set_date();
                $row = $this->set_created_field($row, $date);
                $row = $this->set_updated_field($row, $date);
            }
        }
        // Restore $cleanValidationRules
        $this->clean_validation_rules = $clean_validation_rules;
        $event_data = ['data' => $set];
        if ($this->temp_allow_callbacks) {
            $event_data = $this->trigger('beforeInsertBatch', $event_data);
        }
        $result = $this->do_insert_batch($event_data['data'], $escape, $batch_size, $testing);
        $event_data = ['data' => $event_data['data'], 'result' => $result];
        if ($this->temp_allow_callbacks) {
            // Trigger afterInsert events with the inserted data and new ID
            $this->trigger('afterInsertBatch', $event_data);
        }
        $this->temp_allow_callbacks = $this->allow_callbacks;
        return $result;
    }
    /**
     * Updates a single record in the database. If an object is provided,
     * it will attempt to convert it into an array.
     *
     * @param int|list<int|string>|RawSql|string|null $id
     * @param object|row_array|null                   $row
     *
     * @throws ReflectionException
     */
    public function update($id = null, $row = null): bool
    {
        if ($id !== null) {
            if (!is_array($id)) {
                $id = [$id];
            }
            $this->validate_id($id);
        }
        $row = $this->transform_data_to_array($row, 'update');
        // Validate data before saving.
        if (!$this->skip_validation && !$this->validate($row)) {
            return false;
        }
        // Must be called first, so we don't
        // strip out updated_at values.
        $row = $this->do_protect_fields($row);
        // doProtectFields() can further remove elements from
        // $row, so we need to check for empty dataset again
        if ($row === []) {
            throw Data_Exception::for_empty_dataset('update');
        }
        $row = $this->set_updated_field($row, $this->set_date());
        $event_data = ['id' => $id, 'data' => $row];
        if ($this->temp_allow_callbacks) {
            $event_data = $this->trigger('beforeUpdate', $event_data);
        }
        $event_data = ['id' => $id, 'data' => $event_data['data'], 'result' => $this->do_update($id, $event_data['data'])];
        if ($this->temp_allow_callbacks) {
            $this->trigger('afterUpdate', $event_data);
        }
        $this->temp_allow_callbacks = $this->allow_callbacks;
        return $event_data['result'];
    }
    /**
     * Compiles an update and runs the query.
     *
     * @param list<object|row_array>|null $set       An associative array of insert values.
     * @param string|null                 $index     The where key.
     * @param int                         $batchSize The size of the batch to run.
     * @param bool                        $returnSQL `true` means SQL is returned, `false` will execute the query.
     *
     * @return false|int|list<string> Number of rows affected or `false` on failure, SQL array when test mode.
     *
     * @throws DatabaseException
     * @throws ReflectionException
     */
    public function update_batch(?array $set = null, ?string $index = null, int $batch_size = 100, bool $return_sql = false)
    {
        if (is_array($set)) {
            foreach ($set as &$row) {
                // Save the index value from the original row because
                // transformDataToArray() may strip it when updateOnlyChanged
                // is true.
                $update_index = null;
                if ($this->update_only_changed) {
                    if (is_array($row)) {
                        $update_index = $row[$index] ?? null;
                    } elseif ($row instanceof Entity) {
                        $update_index = $row->to_raw_array()[$index] ?? null;
                    } elseif (is_object($row)) {
                        $update_index = $row->{$index} ?? null;
                    }
                }
                $row = $this->transform_data_to_array($row, 'update');
                // Validate data before saving.
                if (!$this->skip_validation && !$this->validate($row)) {
                    return false;
                }
                // When updateOnlyChanged is true, restore the pre-extracted
                // index into $row. Otherwise read it from the transformed row.
                if ($update_index !== null) {
                    $row[$index] = $update_index;
                } else {
                    $update_index = $row[$index] ?? null;
                }
                if ($update_index === null) {
                    throw new InvalidArgumentException('The index ("' . $index . '") for updateBatch() is missing in the data: ' . json_encode($row));
                }
                // Must be called first so we don't
                // strip out updated_at values.
                $row = $this->do_protect_fields($row);
                // Restore updateIndex value in case it was wiped out
                $row[$index] = $update_index;
                $row = $this->set_updated_field($row, $this->set_date());
            }
        }
        $event_data = ['data' => $set];
        if ($this->temp_allow_callbacks) {
            $event_data = $this->trigger('beforeUpdateBatch', $event_data);
        }
        $result = $this->do_update_batch($event_data['data'], $index, $batch_size, $return_sql);
        $event_data = ['data' => $event_data['data'], 'result' => $result];
        if ($this->temp_allow_callbacks) {
            // Trigger afterInsert events with the inserted data and new ID
            $this->trigger('afterUpdateBatch', $event_data);
        }
        $this->temp_allow_callbacks = $this->allow_callbacks;
        return $result;
    }
    /**
     * Deletes a single record from the database where $id matches.
     *
     * @param int|list<int|string>|RawSql|string|null $id    The rows primary key(s).
     * @param bool                                    $purge Allows overriding the soft deletes setting.
     *
     * @return bool|string Returns a SQL string if in test mode.
     *
     * @throws DatabaseException
     */
    public function delete($id = null, bool $purge = false)
    {
        if ($id !== null) {
            if (!is_array($id)) {
                $id = [$id];
            }
            $this->validate_id($id);
        }
        $event_data = ['id' => $id, 'purge' => $purge];
        if ($this->temp_allow_callbacks) {
            $this->trigger('beforeDelete', $event_data);
        }
        $event_data = ['id' => $id, 'data' => null, 'purge' => $purge, 'result' => $this->do_delete($id, $purge)];
        if ($this->temp_allow_callbacks) {
            $this->trigger('afterDelete', $event_data);
        }
        $this->temp_allow_callbacks = $this->allow_callbacks;
        return $event_data['result'];
    }
    /**
     * Permanently deletes all rows that have been marked as deleted
     * through soft deletes (value of column $deletedField is not null).
     *
     * @return bool|string Returns a SQL string if in test mode.
     */
    public function purge_deleted()
    {
        if (!$this->use_soft_deletes) {
            return true;
        }
        return $this->do_purge_deleted();
    }
    /**
     * Sets $useSoftDeletes value so that we can temporarily override
     * the soft deletes settings. Can be used for all find* methods.
     *
     * @return $this
     */
    public function with_deleted(bool $val = true)
    {
        $this->temp_use_soft_deletes = !$val;
        return $this;
    }
    /**
     * Works with the $this->find* methods to return only the rows that
     * have been deleted.
     *
     * @return $this
     */
    public function only_deleted()
    {
        $this->temp_use_soft_deletes = false;
        $this->do_only_deleted();
        return $this;
    }
    /**
     * Compiles a replace and runs the query.
     *
     * @param row_array|null $row
     * @param bool           $returnSQL `true` means SQL is returned, `false` will execute the query.
     *
     * @return BaseResult|false|Query|string
     */
    public function replace(?array $row = null, bool $return_sql = false)
    {
        // Validate data before saving.
        if ($row !== null && !$this->skip_validation && !$this->validate($row)) {
            return false;
        }
        $row = (array) $row;
        $row = $this->set_created_field($row, $this->set_date());
        $row = $this->set_updated_field($row, $this->set_date());
        return $this->do_replace($row, $return_sql);
    }
    /**
     * Grabs the last error(s) that occurred.
     *
     * If data was validated, it will first check for errors there,
     *  otherwise will try to grab the last error from the Database connection.
     *
     * The return array should be in the following format:
     *  `['source' => 'message']`.
     *
     * @param bool $forceDB Always grab the DB error, not validation.
     *
     * @return array<string, string>
     */
    public function errors(bool $force_db = false)
    {
        if ($this->validation === null) {
            return $this->do_errors();
        }
        // Do we have validation errors?
        if (!$force_db && !$this->skip_validation && ($errors = $this->validation->get_errors()) !== []) {
            return $errors;
        }
        return $this->do_errors();
    }
    /**
     * Works with Pager to get the size and offset parameters.
     * Expects a GET variable (?page=2) that specifies the page of results
     * to display.
     *
     * @param int|null $perPage Items per page.
     * @param string   $group   Will be used by the pagination library to identify a unique pagination set.
     * @param int|null $page    Optional page number (useful when the page number is provided in different way).
     * @param int      $segment Optional URI segment number (if page number is provided by URI segment).
     *
     * @return list<object|row_array>
     */
    public function paginate(?int $per_page = null, string $group = 'default', ?int $page = null, int $segment = 0)
    {
        // Since multiple models may use the Pager, the Pager must be shared.
        $pager = service('pager');
        if ($segment !== 0) {
            $pager->set_segment($segment, $group);
        }
        $page = $page >= 1 ? $page : $pager->get_current_page($group);
        // Store it in the Pager library, so it can be paginated in the views.
        $this->pager = $pager->store($group, $page, $per_page, $this->count_all_results(false), $segment);
        $per_page = $this->pager->get_per_page($group);
        $offset = ($pager->get_current_page($group) - 1) * $per_page;
        return $this->find_all($per_page, $offset);
    }
    /**
     * It could be used when you have to change default or override current allowed fields.
     *
     * @param list<string> $allowedFields Array with names of fields.
     *
     * @return $this
     */
    public function set_allowed_fields(array $allowed_fields)
    {
        $this->allowed_fields = $allowed_fields;
        return $this;
    }
    /**
     * Sets whether or not we should whitelist data set during
     * updates or inserts against $this->availableFields.
     *
     * @return $this
     */
    public function protect(bool $protect = true)
    {
        $this->protect_fields = $protect;
        return $this;
    }
    /**
     * Ensures that only the fields that are allowed to be updated are
     * in the data array.
     *
     * @used-by update() to protect against mass assignment vulnerabilities.
     * @used-by updateBatch() to protect against mass assignment vulnerabilities.
     *
     * @param row_array $row
     *
     * @return row_array
     *
     * @throws DataException
     */
    protected function do_protect_fields(array $row): array
    {
        if (!$this->protect_fields) {
            return $row;
        }
        if ($this->allowed_fields === []) {
            throw Data_Exception::for_invalid_allowed_fields(static::class);
        }
        foreach (array_keys($row) as $key) {
            if (!in_array($key, $this->allowed_fields, true)) {
                unset($row[$key]);
            }
        }
        return $row;
    }
    /**
     * Ensures that only the fields that are allowed to be inserted are in
     * the data array.
     *
     * @used-by insert() to protect against mass assignment vulnerabilities.
     * @used-by insertBatch() to protect against mass assignment vulnerabilities.
     *
     * @param row_array $row
     *
     * @return row_array
     *
     * @throws DataException
     */
    protected function do_protect_fields_for_insert(array $row): array
    {
        return $this->do_protect_fields($row);
    }
    /**
     * Sets the timestamp or current timestamp if null value is passed.
     *
     * @param int|null $userDate An optional PHP timestamp to be converted
     *
     * @return int|string
     *
     * @throws ModelException
     */
    protected function set_date(?int $user_date = null)
    {
        $current_date = $user_date ?? Time::now()->get_timestamp();
        return $this->int_to_date($current_date);
    }
    /**
     * A utility function to allow child models to use the type of
     * date/time format that they prefer. This is primarily used for
     * setting created_at, updated_at and deleted_at values, but can be
     * used by inheriting classes.
     *
     * The available time formats are:
     *  - 'int'      - Stores the date as an integer timestamp.
     *  - 'datetime' - Stores the data in the SQL datetime format.
     *  - 'date'     - Stores the date (only) in the SQL date format.
     *
     * @return int|string
     *
     * @throws ModelException
     */
    protected function int_to_date(int $value)
    {
        return match ($this->date_format) {
            'int' => $value,
            'datetime' => date($this->db->date_format['datetime'], $value),
            'date' => date($this->db->date_format['date'], $value),
            default => throw Model_Exception::for_no_date_format(static::class),
        };
    }
    /**
     * Converts Time value to string using $this->dateFormat.
     *
     * The available time formats are:
     *  - 'int'      - Stores the date as an integer timestamp.
     *  - 'datetime' - Stores the data in the SQL datetime format.
     *  - 'date'     - Stores the date (only) in the SQL date format.
     *
     * @return int|string
     */
    protected function time_to_date(Time $value)
    {
        return match ($this->date_format) {
            'datetime' => $value->format($this->db->date_format['datetime']),
            'date' => $value->format($this->db->date_format['date']),
            'int' => $value->get_timestamp(),
            default => (string) $value,
        };
    }
    /**
     * Set the value of the $skipValidation flag.
     *
     * @return $this
     */
    public function skip_validation(bool $skip = true)
    {
        $this->skip_validation = $skip;
        return $this;
    }
    /**
     * Allows to set (and reset) validation messages.
     * It could be used when you have to change default or override current validate messages.
     *
     * @param array<string, array<string, string>> $validationMessages
     *
     * @return $this
     */
    public function set_validation_messages(array $validation_messages)
    {
        $this->validation_messages = $validation_messages;
        return $this;
    }
    /**
     * Allows to set field wise validation message.
     * It could be used when you have to change default or override current validate messages.
     *
     * @param array<string, string> $fieldMessages
     *
     * @return $this
     */
    public function set_validation_message(string $field, array $field_messages)
    {
        $this->validation_messages[$field] = $field_messages;
        return $this;
    }
    /**
     * Allows to set (and reset) validation rules.
     * It could be used when you have to change default or override current validate rules.
     *
     * @param array<string, array<string, array<string, string>|string>|string> $validationRules
     *
     * @return $this
     */
    public function set_validation_rules(array $validation_rules)
    {
        $this->validation_rules = $validation_rules;
        return $this;
    }
    /**
     * Allows to set field wise validation rules.
     * It could be used when you have to change default or override current validate rules.
     *
     * @param array<string, array<string, string>|string>|string $fieldRules
     *
     * @return $this
     */
    public function set_validation_rule(string $field, $field_rules)
    {
        $rules = $this->validation_rules;
        // ValidationRules can be either a string, which is the group name,
        // or an array of rules.
        if (is_string($rules)) {
            $this->ensure_validation();
            [$rules, $custom_errors] = $this->validation->load_rule_group($rules);
            $this->validation_rules = $rules;
            $this->validation_messages += $custom_errors;
        }
        $this->validation_rules[$field] = $field_rules;
        return $this;
    }
    /**
     * Should validation rules be removed before saving?
     * Most handy when doing updates.
     *
     * @return $this
     */
    public function clean_rules(bool $choice = false)
    {
        $this->clean_validation_rules = $choice;
        return $this;
    }
    /**
     * Validate the row data against the validation rules (or the validation group)
     * specified in the class property, $validationRules.
     *
     * @param object|row_array $row
     */
    public function validate($row): bool
    {
        if ($this->skip_validation) {
            return true;
        }
        $rules = $this->get_validation_rules();
        if ($rules === []) {
            return true;
        }
        // Validation requires array, so cast away.
        if (is_object($row)) {
            $row = (array) $row;
        }
        if ($row === []) {
            return true;
        }
        $rules = $this->clean_validation_rules ? $this->clean_validation_rules($rules, $row) : $rules;
        // If no data existed that needs validation
        // our job is done here.
        if ($rules === []) {
            return true;
        }
        $this->ensure_validation();
        $this->validation->reset()->set_rules($rules, $this->validation_messages);
        return $this->validation->run($row, null, $this->db_group);
    }
    /**
     * Returns the model's defined validation rules so that they
     * can be used elsewhere, if needed.
     *
     * @param array{only?: list<string>, except?: list<string>} $options Filter the list of rules
     *
     * @return array<string, array<string, array<string, string>|string>|string>
     */
    public function get_validation_rules(array $options = []): array
    {
        $rules = $this->validation_rules;
        // ValidationRules can be either a string, which is the group name,
        // or an array of rules.
        if (is_string($rules)) {
            $this->ensure_validation();
            [$rules, $custom_errors] = $this->validation->load_rule_group($rules);
            $this->validation_messages += $custom_errors;
        }
        if (isset($options['except'])) {
            $rules = array_diff_key($rules, array_flip($options['except']));
        } elseif (isset($options['only'])) {
            $rules = array_intersect_key($rules, array_flip($options['only']));
        }
        return $rules;
    }
    protected function ensure_validation(): void
    {
        if ($this->validation === null) {
            $this->validation = service('validation', null, false);
        }
    }
    /**
     * Returns the model's validation messages, so they
     * can be used elsewhere, if needed.
     *
     * @return array<string, array<string, string>>
     */
    public function get_validation_messages(): array
    {
        return $this->validation_messages;
    }
    /**
     * Removes any rules that apply to fields that have not been set
     * currently so that rules don't block updating when only updating
     * a partial row.
     *
     * @param array<string, array<string, array<string, string>|string>|string> $rules
     * @param row_array                                                         $row
     *
     * @return array<string, array<string, array<string, string>|string>|string>
     */
    protected function clean_validation_rules(array $rules, array $row): array
    {
        if ($row === []) {
            return [];
        }
        foreach (array_keys($rules) as $field) {
            if (!array_key_exists($field, $row)) {
                unset($rules[$field]);
            }
        }
        return $rules;
    }
    /**
     * Sets $tempAllowCallbacks value so that we can temporarily override
     * the setting. Resets after the next method that uses triggers.
     *
     * @return $this
     */
    public function allow_callbacks(bool $val = true)
    {
        $this->temp_allow_callbacks = $val;
        return $this;
    }
    /**
     * A simple event trigger for Model Events that allows additional
     * data manipulation within the model. Specifically intended for
     * usage by child models this can be used to format data,
     * save/load related classes, etc.
     *
     * It is the responsibility of the callback methods to return
     * the data itself.
     *
     * Each $eventData array MUST have a 'data' key with the relevant
     * data for callback methods (like an array of key/value pairs to insert
     * or update, an array of results, etc.)
     *
     * If callbacks are not allowed then returns $eventData immediately.
     *
     * @template TEventData of array<string, mixed>
     *
     * @param string     $event     Valid property of the model event: $this->before*, $this->after*, etc.
     * @param TEventData $eventData
     *
     * @return TEventData
     *
     * @throws DataException
     */
    protected function trigger(string $event, array $event_data)
    {
        // Ensure it's a valid event
        if (!isset($this->{$event}) || $this->{$event} === []) {
            return $event_data;
        }
        foreach ($this->{$event} as $callback) {
            if (!method_exists($this, $callback)) {
                throw Data_Exception::for_invalid_method_triggered($callback);
            }
            $event_data = $this->{$callback}($event_data);
        }
        return $event_data;
    }
    /**
     * Sets the return type of the results to be as an associative array.
     *
     * @return $this
     */
    public function as_array()
    {
        $this->temp_return_type = 'array';
        return $this;
    }
    /**
     * Sets the return type to be of the specified type of object.
     * Defaults to a simple object, but can be any class that has
     * class vars with the same name as the collection columns,
     * or at least allows them to be created.
     *
     * @param 'object'|class-string $class
     *
     * @return $this
     */
    public function as_object(string $class = 'object')
    {
        $this->temp_return_type = $class;
        return $this;
    }
    /**
     * Takes a class and returns an array of its public and protected
     * properties as an array suitable for use in creates and updates.
     * This method uses `$this->objectToRawArray()` internally and does conversion
     * to string on all Time instances.
     *
     * @param object $object
     * @param bool   $onlyChanged Returns only the changed properties.
     * @param bool   $recursive   If `true`, inner entities will be cast as array as well.
     *
     * @return array<string, mixed>
     *
     * @throws ReflectionException
     */
    protected function object_to_array($object, bool $only_changed = true, bool $recursive = false): array
    {
        $properties = $this->object_to_raw_array($object, $only_changed, $recursive);
        // Convert any Time instances to appropriate $dateFormat
        return $this->time_to_string($properties);
    }
    /**
     * Convert any Time instances to appropriate $dateFormat.
     *
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    protected function time_to_string(array $properties): array
    {
        if ($properties === []) {
            return [];
        }
        return array_map(function ($value) {
            if ($value instanceof Time) {
                return $this->time_to_date($value);
            }
            return $value;
        }, $properties);
    }
    /**
     * Takes a class and returns an array of its public and protected
     * properties as an array with raw values.
     *
     * @param object $object
     * @param bool   $onlyChanged Returns only the changed properties.
     * @param bool   $recursive   If `true`, inner entities will be cast as array as well.
     *
     * @return array<string, mixed> Array with raw values
     *
     * @throws ReflectionException
     */
    protected function object_to_raw_array($object, bool $only_changed = true, bool $recursive = false): array
    {
        // Entity::toRawArray() returns array
        if (method_exists($object, 'toRawArray')) {
            $properties = $object->to_raw_array($only_changed, $recursive);
        } else {
            $mirror = new ReflectionClass($object);
            $props = $mirror->get_properties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
            $properties = [];
            // Loop over each property,
            // saving the name/value in a new array we can return
            foreach ($props as $prop) {
                $properties[$prop->get_name()] = $prop->get_value($object);
            }
        }
        return $properties;
    }
    /**
     * Transform data to array.
     *
     * @param object|row_array|null $row
     *
     * @return array<int|string, mixed>
     *
     * @throws DataException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     *
     * @used-by insert()
     * @used-by insertBatch()
     * @used-by update()
     * @used-by updateBatch()
     */
    protected function transform_data_to_array($row, string $type): array
    {
        if (!in_array($type, ['insert', 'update'], true)) {
            throw new InvalidArgumentException(sprintf('Invalid type "%s" used upon transforming data to array.', $type));
        }
        if (!$this->allow_empty_inserts && ($row === null || (array) $row === [])) {
            throw Data_Exception::for_empty_dataset($type);
        }
        // If it validates with entire rules, all fields are needed.
        if ($this->skip_validation === false && $this->clean_validation_rules === false) {
            $only_changed = false;
        } else {
            $only_changed = $type === 'update' && $this->update_only_changed;
        }
        if ($this->use_casts()) {
            if (is_array($row)) {
                $row = $this->converter->to_data_source($row);
            } elseif ($row instanceof stdClass) {
                $row = (array) $row;
                $row = $this->converter->to_data_source($row);
            } elseif ($row instanceof Entity) {
                $row = $this->converter->extract($row, $only_changed);
            } elseif (is_object($row)) {
                $row = $this->converter->extract($row, $only_changed);
            }
        } elseif (is_object($row) && !$row instanceof stdClass) {
            $row = $this->object_to_array($row, $only_changed, true);
        }
        // If it's still a stdClass, go ahead and convert to
        // an array so doProtectFields and other model methods
        // don't have to do special checks.
        if (is_object($row)) {
            $row = (array) $row;
        }
        // If it's still empty here, means $row is no change or is empty object
        if (!$this->allow_empty_inserts && ($row === null || $row === [])) {
            throw Data_Exception::for_empty_dataset($type);
        }
        // Convert any Time instances to appropriate $dateFormat
        return $this->time_to_string($row);
    }
    /**
     * Provides the DB connection and model's properties.
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }
        return $this->db->{$name} ?? null;
    }
    /**
     * Checks for the existence of properties across this model, and DB connection.
     */
    public function __isset(string $name): bool
    {
        if (property_exists($this, $name)) {
            return true;
        }
        return isset($this->db->{$name});
    }
    /**
     * Provides direct access to method in the database connection.
     *
     * @param array<int|string, mixed> $params
     *
     * @return mixed
     */
    public function __call(string $name, array $params)
    {
        if (method_exists($this->db, $name)) {
            return $this->db->{$name}(...$params);
        }
        return null;
    }
    /**
     * Sets $allowEmptyInserts.
     */
    public function allow_empty_inserts(bool $value = true): self
    {
        $this->allow_empty_inserts = $value;
        return $this;
    }
    /**
     * Converts database data array to return type value.
     *
     * @param array<string, mixed>          $row        Raw data from database.
     * @param 'array'|'object'|class-string $returnType
     *
     * @return array<string, mixed>|object
     */
    protected function convert_to_return_type(array $row, string $return_type): array|object
    {
        if ($return_type === 'array') {
            return $this->converter->from_data_source($row);
        }
        if ($return_type === 'object') {
            return (object) $this->converter->from_data_source($row);
        }
        return $this->converter->reconstruct($return_type, $row);
    }
}