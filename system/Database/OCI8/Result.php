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
namespace Code_Igniter\Database\OCI8;

use Code_Igniter\Database\Base_Result;
use Code_Igniter\Entity\Entity;
use stdClass;
/**
 * Result for OCI8
 *
 * @extends BaseResult<resource, resource>
 */
class Result extends Base_Result
{
    /**
     * Gets the number of fields in the result set.
     */
    public function get_field_count(): int
    {
        return oci_num_fields($this->result_id);
    }
    /**
     * Generates an array of column names in the result set.
     */
    public function get_field_names(): array
    {
        return array_map(fn($field_index): false|string => oci_field_name($this->result_id, $field_index), range(1, $this->get_field_count()));
    }
    /**
     * Generates an array of objects representing field meta-data.
     */
    public function get_field_data(): array
    {
        return array_map(fn($field_index) => (object) ['name' => oci_field_name($this->result_id, $field_index), 'type' => oci_field_type($this->result_id, $field_index), 'max_length' => oci_field_size($this->result_id, $field_index)], range(1, $this->get_field_count()));
    }
    /**
     * Frees the current result.
     *
     * @return void
     */
    public function free_result()
    {
        if (is_resource($this->result_id)) {
            oci_free_statement($this->result_id);
            $this->result_id = false;
        }
    }
    /**
     * Moves the internal pointer to the desired offset. This is called
     * internally before fetching results to make sure the result set
     * starts at zero.
     *
     * @return false
     */
    public function data_seek(int $n = 0)
    {
        // We can't support data seek by oci
        return false;
    }
    /**
     * Returns the result set as an array.
     *
     * Overridden by driver classes.
     *
     * @return array|false
     */
    protected function fetch_assoc()
    {
        return oci_fetch_assoc($this->result_id);
    }
    /**
     * Returns the result set as an object.
     *
     * Overridden by child classes.
     *
     * @return Entity|false|object|stdClass
     */
    protected function fetch_object(string $class_name = 'stdClass')
    {
        $row = oci_fetch_object($this->result_id);
        if ($class_name === 'stdClass' || !$row) {
            return $row;
        }
        if (is_subclass_of($class_name, Entity::class)) {
            return (new $class_name())->inject_raw_data((array) $row);
        }
        $instance = new $class_name();
        foreach (get_object_vars($row) as $key => $value) {
            $instance->{$key} = $value;
        }
        return $instance;
    }
}