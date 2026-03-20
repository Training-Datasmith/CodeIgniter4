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

use stdClass;
/**
 * @template TConnection
 * @template TResult
 */
interface Result_Interface
{
    /**
     * Retrieve the results of the query. Typically an array of
     * individual data rows, which can be either an 'array', an
     * 'object', or a custom class name.
     *
     * @param string $type The row type. Either 'array', 'object', or a class name to use
     */
    public function get_result(string $type = 'object'): array;
    /**
     * Returns the results as an array of custom objects.
     *
     * @param string $className The name of the class to use.
     *
     * @return array
     */
    public function get_custom_result_object(string $class_name);
    /**
     * Returns the results as an array of arrays.
     *
     * If no results, an empty array is returned.
     */
    public function get_result_array(): array;
    /**
     * Returns the results as an array of objects.
     *
     * If no results, an empty array is returned.
     */
    public function get_result_object(): array;
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
    public function get_row($n = 0, string $type = 'object');
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
    public function get_custom_row_object(int $n, string $class_name);
    /**
     * Returns a single row from the results as an array.
     *
     * If row doesn't exist, returns null.
     *
     * @return array|null
     */
    public function get_row_array(int $n = 0);
    /**
     * Returns a single row from the results as an object.
     *
     * If row doesn't exist, returns null.
     *
     * @return object|stdClass|null
     */
    public function get_row_object(int $n = 0);
    /**
     * Assigns an item into a particular column slot.
     *
     * @param array|string               $key
     * @param array|object|stdClass|null $value
     *
     * @return void
     */
    public function set_row($key, $value = null);
    /**
     * Returns the "first" row of the current results.
     *
     * @return array|object|null
     */
    public function get_first_row(string $type = 'object');
    /**
     * Returns the "last" row of the current results.
     *
     * @return array|object|null
     */
    public function get_last_row(string $type = 'object');
    /**
     * Returns the "next" row of the current results.
     *
     * @return array|object|null
     */
    public function get_next_row(string $type = 'object');
    /**
     * Returns the "previous" row of the current results.
     *
     * @return array|object|null
     */
    public function get_previous_row(string $type = 'object');
    /**
     * Returns number of rows in the result set.
     */
    public function get_num_rows(): int;
    /**
     * Returns an unbuffered row and move the pointer to the next row.
     *
     * @return array|object|null
     */
    public function get_unbuffered_row(string $type = 'object');
    /**
     * Gets the number of fields in the result set.
     */
    public function get_field_count(): int;
    /**
     * Generates an array of column names in the result set.
     */
    public function get_field_names(): array;
    /**
     * Generates an array of objects representing field meta-data.
     */
    public function get_field_data(): array;
    /**
     * Frees the current result.
     *
     * @return void
     */
    public function free_result();
    /**
     * Moves the internal pointer to the desired offset. This is called
     * internally before fetching results to make sure the result set
     * starts at zero.
     *
     * @return bool
     */
    public function data_seek(int $n = 0);
}