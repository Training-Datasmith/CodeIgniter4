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
namespace Code_Igniter\Database\Postgre;

use Code_Igniter\Database\Base_Result;
use Code_Igniter\Entity\Entity;
use Pg_Sql\Connection as PgSqlConnection;
use Pg_Sql\Result as PgSqlResult;
use stdClass;
/**
 * Result for Postgre
 *
 * @extends BaseResult<PgSqlConnection, PgSqlResult>
 */
class Result extends Base_Result
{
    /**
     * Gets the number of fields in the result set.
     */
    public function get_field_count(): int
    {
        return pg_num_fields($this->result_id);
    }
    /**
     * Generates an array of column names in the result set.
     */
    public function get_field_names(): array
    {
        $field_names = [];
        for ($i = 0, $c = $this->get_field_count(); $i < $c; $i++) {
            $field_names[] = pg_field_name($this->result_id, $i);
        }
        return $field_names;
    }
    /**
     * Generates an array of objects representing field meta-data.
     */
    public function get_field_data(): array
    {
        $ret_val = [];
        for ($i = 0, $c = $this->get_field_count(); $i < $c; $i++) {
            $ret_val[$i] = new stdClass();
            $ret_val[$i]->name = pg_field_name($this->result_id, $i);
            $ret_val[$i]->type = pg_field_type_oid($this->result_id, $i);
            $ret_val[$i]->type_name = pg_field_type($this->result_id, $i);
            $ret_val[$i]->max_length = pg_field_size($this->result_id, $i);
            $ret_val[$i]->length = $ret_val[$i]->max_length;
            // $retVal[$i]->primary_key = (int)($fieldData[$i]->flags & 2);
            // $retVal[$i]->default     = $fieldData[$i]->def;
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
        if ($this->result_id !== false) {
            pg_free_result($this->result_id);
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
        return pg_result_seek($this->result_id, $n);
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
        return pg_fetch_assoc($this->result_id);
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
        return pg_fetch_object($this->result_id, null, $class_name);
    }
    /**
     * Returns the number of rows in the resultID (i.e., PostgreSQL query result resource)
     */
    public function get_num_rows(): int
    {
        if (!is_int($this->num_rows)) {
            $this->num_rows = pg_num_rows($this->result_id);
        }
        return $this->num_rows;
    }
}