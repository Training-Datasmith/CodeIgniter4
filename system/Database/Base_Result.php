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

use Code_Igniter\Entity\Entity;
use stdClass;
/**
 * @template TConnection
 * @template TResult
 *
 * @implements ResultInterface<TConnection, TResult>
 */
abstract class Base_Result implements Result_Interface
{
    /**
     * Connection ID
     *
     * @var TConnection
     */
    public $conn_id;
    /**
     * Result ID
     *
     * @var false|TResult
     */
    public $result_id;
    /**
     * Result Array
     *
     * @var list<array>
     */
    public $result_array = [];
    /**
     * Result Object
     *
     * @var list<object>
     */
    public $result_object = [];
    /**
     * Custom Result Object
     *
     * @var array
     */
    public $custom_result_object = [];
    /**
     * Current Row index
     *
     * @var int
     */
    public $current_row = 0;
    /**
     * The number of records in the query result
     *
     * @var int|null
     */
    protected $num_rows;
    /**
     * Row data
     *
     * @var array|null
     */
    public $row_data;
    /**
     * Constructor
     *
     * @param TConnection $connID
     * @param TResult     $resultID
     */
    public function __construct(&$conn_id, &$result_id)
    {
        $this->conn_id = $conn_id;
        $this->result_id = $result_id;
    }
    /**
     * Retrieve the results of the query. Typically an array of
     * individual data rows, which can be either an 'array', an
     * 'object', or a custom class name.
     *
     * @param string $type The row type. Either 'array', 'object', or a class name to use
     */
    public function get_result(string $type = 'object'): array
    {
        if ($type === 'array') {
            return $this->get_result_array();
        }
        if ($type === 'object') {
            return $this->get_result_object();
        }
        return $this->get_custom_result_object($type);
    }
    /**
     * Returns the results as an array of custom objects.
     *
     * @param class-string $className
     *
     * @return array
     */
    public function get_custom_result_object(string $class_name)
    {
        if (isset($this->custom_result_object[$class_name])) {
            return $this->custom_result_object[$class_name];
        }
        if (!$this->is_valid_result_id()) {
            return [];
        }
        // Don't fetch the result set again if we already have it
        $_data = null;
        if (($c = count($this->result_array)) > 0) {
            $_data = 'resultArray';
        } elseif (($c = count($this->result_object)) > 0) {
            $_data = 'resultObject';
        }
        if ($_data !== null) {
            for ($i = 0; $i < $c; $i++) {
                $this->custom_result_object[$class_name][$i] = new $class_name();
                foreach ($this->{$_data}[$i] as $key => $value) {
                    $this->custom_result_object[$class_name][$i]->{$key} = $value;
                }
            }
            return $this->custom_result_object[$class_name];
        }
        if ($this->row_data !== null) {
            $this->data_seek();
        }
        $this->custom_result_object[$class_name] = [];
        while ($row = $this->fetch_object($class_name)) {
            if (!is_subclass_of($row, Entity::class) && method_exists($row, 'syncOriginal')) {
                $row->sync_original();
            }
            $this->custom_result_object[$class_name][] = $row;
        }
        return $this->custom_result_object[$class_name];
    }
    /**
     * Returns the results as an array of arrays.
     *
     * If no results, an empty array is returned.
     */
    public function get_result_array(): array
    {
        if ($this->result_array !== []) {
            return $this->result_array;
        }
        // In the event that query caching is on, the result_id variable
        // will not be a valid resource so we'll simply return an empty
        // array.
        if (!$this->is_valid_result_id()) {
            return [];
        }
        if ($this->result_object !== []) {
            foreach ($this->result_object as $row) {
                $this->result_array[] = (array) $row;
            }
            return $this->result_array;
        }
        if ($this->row_data !== null) {
            $this->data_seek();
        }
        while ($row = $this->fetch_assoc()) {
            $this->result_array[] = $row;
        }
        return $this->result_array;
    }
    /**
     * Returns the results as an array of objects.
     *
     * If no results, an empty array is returned.
     *
     * @return list<stdClass>
     */
    public function get_result_object(): array
    {
        if ($this->result_object !== []) {
            return $this->result_object;
        }
        // In the event that query caching is on, the result_id variable
        // will not be a valid resource so we'll simply return an empty
        // array.
        if (!$this->is_valid_result_id()) {
            return [];
        }
        if ($this->result_array !== []) {
            foreach ($this->result_array as $row) {
                $this->result_object[] = (object) $row;
            }
            return $this->result_object;
        }
        if ($this->row_data !== null) {
            $this->data_seek();
        }
        while ($row = $this->fetch_object()) {
            if (!is_subclass_of($row, Entity::class) && method_exists($row, 'syncOriginal')) {
                $row->sync_original();
            }
            $this->result_object[] = $row;
        }
        return $this->result_object;
    }
    /**
     * Wrapper object to return a row as either an array, an object, or
     * a custom class.
     *
     * If the row doesn't exist, returns null.
     *
     * @template T of object
     *
     * @param int|string                       $n    The index of the results to return, or column name.
     * @param 'array'|'object'|class-string<T> $type The type of result object. 'array', 'object' or class name.
     *
     * @return ($n is string ? float|int|string|null : ($type is 'object' ? stdClass|null : ($type is 'array' ? array|null : T|null)))
     */
    public function get_row($n = 0, string $type = 'object')
    {
        // $n is a column name.
        if (!is_numeric($n)) {
            // We cache the row data for subsequent uses
            if (!is_array($this->row_data)) {
                $this->row_data = $this->get_row_array();
            }
            // array_key_exists() instead of isset() to allow for NULL values
            if (empty($this->row_data) || !array_key_exists($n, $this->row_data)) {
                return null;
            }
            return $this->row_data[$n];
        }
        if ($type === 'object') {
            return $this->get_row_object($n);
        }
        if ($type === 'array') {
            return $this->get_row_array($n);
        }
        return $this->get_custom_row_object($n, $type);
    }
    /**
     * Returns a row as a custom class instance.
     *
     * If the row doesn't exist, returns null.
     *
     * @template T of object
     *
     * @param int             $n         The index of the results to return.
     * @param class-string<T> $className
     *
     * @return T|null
     */
    public function get_custom_row_object(int $n, string $class_name)
    {
        if (!isset($this->custom_result_object[$class_name])) {
            $this->get_custom_result_object($class_name);
        }
        if (empty($this->custom_result_object[$class_name])) {
            return null;
        }
        if ($n !== $this->current_row && isset($this->custom_result_object[$class_name][$n])) {
            $this->current_row = $n;
        }
        return $this->custom_result_object[$class_name][$this->current_row];
    }
    /**
     * Returns a single row from the results as an array.
     *
     * If row doesn't exist, returns null.
     *
     * @return array|null
     */
    public function get_row_array(int $n = 0)
    {
        $result = $this->get_result_array();
        if ($result === []) {
            return null;
        }
        if ($n !== $this->current_row && isset($result[$n])) {
            $this->current_row = $n;
        }
        return $result[$this->current_row];
    }
    /**
     * Returns a single row from the results as an object.
     *
     * If row doesn't exist, returns null.
     *
     * @return object|stdClass|null
     */
    public function get_row_object(int $n = 0)
    {
        $result = $this->get_result_object();
        if ($result === []) {
            return null;
        }
        if ($n !== $this->custom_result_object && isset($result[$n])) {
            $this->current_row = $n;
        }
        return $result[$this->current_row];
    }
    /**
     * Assigns an item into a particular column slot.
     *
     * @param array|string               $key
     * @param array|object|stdClass|null $value
     *
     * @return void
     */
    public function set_row($key, $value = null)
    {
        // We cache the row data for subsequent uses
        if (!is_array($this->row_data)) {
            $this->row_data = $this->get_row_array();
        }
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->row_data[$k] = $v;
            }
            return;
        }
        if ($key !== '' && $value !== null) {
            $this->row_data[$key] = $value;
        }
    }
    /**
     * Returns the "first" row of the current results.
     *
     * @return array|object|null
     */
    public function get_first_row(string $type = 'object')
    {
        $result = $this->get_result($type);
        return $result === [] ? null : $result[0];
    }
    /**
     * Returns the "last" row of the current results.
     *
     * @return array|object|null
     */
    public function get_last_row(string $type = 'object')
    {
        $result = $this->get_result($type);
        return $result === [] ? null : $result[count($result) - 1];
    }
    /**
     * Returns the "next" row of the current results.
     *
     * @return array|object|null
     */
    public function get_next_row(string $type = 'object')
    {
        $result = $this->get_result($type);
        if ($result === []) {
            return null;
        }
        return isset($result[$this->current_row + 1]) ? $result[++$this->current_row] : null;
    }
    /**
     * Returns the "previous" row of the current results.
     *
     * @return array|object|null
     */
    public function get_previous_row(string $type = 'object')
    {
        $result = $this->get_result($type);
        if ($result === []) {
            return null;
        }
        if (isset($result[$this->current_row - 1])) {
            $this->current_row--;
        }
        return $result[$this->current_row];
    }
    /**
     * Returns an unbuffered row and move the pointer to the next row.
     *
     * @return array|object|null
     */
    public function get_unbuffered_row(string $type = 'object')
    {
        if ($type === 'array') {
            return $this->fetch_assoc();
        }
        if ($type === 'object') {
            return $this->fetch_object();
        }
        return $this->fetch_object($type);
    }
    /**
     * Number of rows in the result set; checks for previous count, falls
     * back on counting resultArray or resultObject, finally fetching resultArray
     * if nothing was previously fetched
     */
    public function get_num_rows(): int
    {
        if (is_int($this->num_rows)) {
            return $this->num_rows;
        }
        if ($this->result_array !== []) {
            return $this->num_rows = count($this->result_array);
        }
        if ($this->result_object !== []) {
            return $this->num_rows = count($this->result_object);
        }
        return $this->num_rows = count($this->get_result_array());
    }
    private function is_valid_result_id(): bool
    {
        return is_resource($this->result_id) || is_object($this->result_id);
    }
    /**
     * Gets the number of fields in the result set.
     */
    abstract public function get_field_count(): int;
    /**
     * Generates an array of column names in the result set.
     */
    abstract public function get_field_names(): array;
    /**
     * Generates an array of objects representing field meta-data.
     */
    abstract public function get_field_data(): array;
    /**
     * Frees the current result.
     *
     * @return void
     */
    abstract public function free_result();
    /**
     * Moves the internal pointer to the desired offset. This is called
     * internally before fetching results to make sure the result set
     * starts at zero.
     *
     * @return bool
     */
    abstract public function data_seek(int $n = 0);
    /**
     * Returns the result set as an array.
     *
     * Overridden by driver classes.
     *
     * @return array|false|null
     */
    abstract protected function fetch_assoc();
    /**
     * Returns the result set as an object.
     *
     * @param class-string $className
     *
     * @return false|object
     */
    abstract protected function fetch_object(string $class_name = stdClass::class);
}