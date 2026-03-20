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
namespace Code_Igniter\Database\My_Sq_Li;

use Code_Igniter\Database\Base_Result;
use Code_Igniter\Entity\Entity;
use mysqli;
use mysqli_result;
use stdClass;
/**
 * Result for MySQLi
 *
 * @extends BaseResult<mysqli, mysqli_result>
 */
class Result extends Base_Result
{
    /**
     * Gets the number of fields in the result set.
     */
    public function get_field_count(): int
    {
        return $this->result_id->field_count;
    }
    /**
     * Generates an array of column names in the result set.
     */
    public function get_field_names(): array
    {
        $field_names = [];
        $this->result_id->field_seek(0);
        while ($field = $this->result_id->fetch_field()) {
            $field_names[] = $field->name;
        }
        return $field_names;
    }
    /**
     * Generates an array of objects representing field meta-data.
     */
    public function get_field_data(): array
    {
        static $data_types = [MYSQLI_TYPE_DECIMAL => 'decimal', MYSQLI_TYPE_NEWDECIMAL => 'newdecimal', MYSQLI_TYPE_FLOAT => 'float', MYSQLI_TYPE_DOUBLE => 'double', MYSQLI_TYPE_BIT => 'bit', MYSQLI_TYPE_SHORT => 'short', MYSQLI_TYPE_LONG => 'long', MYSQLI_TYPE_LONGLONG => 'longlong', MYSQLI_TYPE_INT24 => 'int24', MYSQLI_TYPE_YEAR => 'year', MYSQLI_TYPE_TIMESTAMP => 'timestamp', MYSQLI_TYPE_DATE => 'date', MYSQLI_TYPE_TIME => 'time', MYSQLI_TYPE_DATETIME => 'datetime', MYSQLI_TYPE_NEWDATE => 'newdate', MYSQLI_TYPE_SET => 'set', MYSQLI_TYPE_VAR_STRING => 'var_string', MYSQLI_TYPE_STRING => 'string', MYSQLI_TYPE_GEOMETRY => 'geometry', MYSQLI_TYPE_TINY_BLOB => 'tiny_blob', MYSQLI_TYPE_MEDIUM_BLOB => 'medium_blob', MYSQLI_TYPE_LONG_BLOB => 'long_blob', MYSQLI_TYPE_BLOB => 'blob'];
        $ret_val = [];
        $field_data = $this->result_id->fetch_fields();
        foreach ($field_data as $i => $data) {
            $ret_val[$i] = new stdClass();
            $ret_val[$i]->name = $data->name;
            $ret_val[$i]->type = $data->type;
            $ret_val[$i]->type_name = in_array($data->type, [1, 247], true) ? 'char' : $data_types[$data->type] ?? null;
            $ret_val[$i]->max_length = $data->max_length;
            $ret_val[$i]->primary_key = $data->flags & 2;
            $ret_val[$i]->length = $data->length;
            $ret_val[$i]->default = $data->def;
        }
        return $ret_val;
    }
    /**
     * Frees the current result.
     *
     * @return void
     */
    public function free_result()
    {
        if (is_object($this->result_id)) {
            $this->result_id->free();
            $this->result_id = false;
        }
    }
    /**
     * Moves the internal pointer to the desired offset. This is called
     * internally before fetching results to make sure the result set
     * starts at zero.
     *
     * @return bool
     */
    public function data_seek(int $n = 0)
    {
        return $this->result_id->data_seek($n);
    }
    /**
     * Returns the result set as an array.
     *
     * Overridden by driver classes.
     *
     * @return array|false|null
     */
    protected function fetch_assoc()
    {
        return $this->result_id->fetch_assoc();
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
        if (is_subclass_of($class_name, Entity::class)) {
            return empty($data = $this->fetch_assoc()) ? false : (new $class_name())->inject_raw_data($data);
        }
        return $this->result_id->fetch_object($class_name);
    }
    /**
     * Returns the number of rows in the resultID (i.e., mysqli_result object)
     */
    public function get_num_rows(): int
    {
        if (!is_int($this->num_rows)) {
            $this->num_rows = $this->result_id->num_rows;
        }
        return $this->num_rows;
    }
}