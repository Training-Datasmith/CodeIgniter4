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

use Code_Igniter\Database\Base_Result;
use Code_Igniter\Entity\Entity;
use stdClass;
/**
 * Result for SQLSRV
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
        return @sqlsrv_num_fields($this->result_id);
    }
    /**
     * Generates an array of column names in the result set.
     */
    public function get_field_names(): array
    {
        $field_names = [];
        foreach (sqlsrv_field_metadata($this->result_id) as $field) {
            $field_names[] = $field['Name'];
        }
        return $field_names;
    }
    /**
     * Generates an array of objects representing field meta-data.
     */
    public function get_field_data(): array
    {
        static $data_types = [SQLSRV_SQLTYPE_BIGINT => 'bigint', SQLSRV_SQLTYPE_BIT => 'bit', SQLSRV_SQLTYPE_CHAR => 'char', SQLSRV_SQLTYPE_DATE => 'date', SQLSRV_SQLTYPE_DATETIME => 'datetime', SQLSRV_SQLTYPE_DATETIME2 => 'datetime2', SQLSRV_SQLTYPE_DATETIMEOFFSET => 'datetimeoffset', SQLSRV_SQLTYPE_DECIMAL => 'decimal', SQLSRV_SQLTYPE_FLOAT => 'float', SQLSRV_SQLTYPE_IMAGE => 'image', SQLSRV_SQLTYPE_INT => 'int', SQLSRV_SQLTYPE_MONEY => 'money', SQLSRV_SQLTYPE_NCHAR => 'nchar', SQLSRV_SQLTYPE_NUMERIC => 'numeric', SQLSRV_SQLTYPE_NVARCHAR => 'nvarchar', SQLSRV_SQLTYPE_NTEXT => 'ntext', SQLSRV_SQLTYPE_REAL => 'real', SQLSRV_SQLTYPE_SMALLDATETIME => 'smalldatetime', SQLSRV_SQLTYPE_SMALLINT => 'smallint', SQLSRV_SQLTYPE_SMALLMONEY => 'smallmoney', SQLSRV_SQLTYPE_TEXT => 'text', SQLSRV_SQLTYPE_TIME => 'time', SQLSRV_SQLTYPE_TIMESTAMP => 'timestamp', SQLSRV_SQLTYPE_TINYINT => 'tinyint', SQLSRV_SQLTYPE_UNIQUEIDENTIFIER => 'uniqueidentifier', SQLSRV_SQLTYPE_UDT => 'udt', SQLSRV_SQLTYPE_VARBINARY => 'varbinary', SQLSRV_SQLTYPE_VARCHAR => 'varchar', SQLSRV_SQLTYPE_XML => 'xml'];
        $ret_val = [];
        foreach (sqlsrv_field_metadata($this->result_id) as $i => $field) {
            $ret_val[$i] = new stdClass();
            $ret_val[$i]->name = $field['Name'];
            $ret_val[$i]->type = $field['Type'];
            $ret_val[$i]->type_name = $data_types[$field['Type']] ?? null;
            $ret_val[$i]->max_length = $field['Size'];
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
        if (is_resource($this->result_id)) {
            sqlsrv_free_stmt($this->result_id);
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
        if ($n > 0) {
            for ($i = 0; $i < $n; $i++) {
                if (sqlsrv_fetch($this->result_id) === false) {
                    return false;
                }
            }
        }
        return true;
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
        return sqlsrv_fetch_array($this->result_id, SQLSRV_FETCH_ASSOC);
    }
    /**
     * Returns the result set as an object.
     *
     * @return Entity|false|object|stdClass
     */
    protected function fetch_object(string $class_name = 'stdClass')
    {
        if (is_subclass_of($class_name, Entity::class)) {
            return empty($data = $this->fetch_assoc()) ? false : (new $class_name())->inject_raw_data($data);
        }
        return sqlsrv_fetch_object($this->result_id, $class_name);
    }
    /**
     * Returns the number of rows in the resultID (i.e., SQLSRV query result resource)
     */
    public function get_num_rows(): int
    {
        if (!is_int($this->num_rows)) {
            $this->num_rows = sqlsrv_num_rows($this->result_id);
        }
        return $this->num_rows;
    }
}