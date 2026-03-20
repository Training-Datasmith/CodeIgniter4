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

use Code_Igniter\Database\Base_Prepared_Query;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Exceptions\BadMethodCallException;
use Oci_Lob;
/**
 * Prepared query for OCI8
 *
 * @extends BasePreparedQuery<resource, resource, resource>
 */
class Prepared_Query extends Base_Prepared_Query
{
    /**
     * A reference to the db connection to use.
     *
     * @var Connection
     */
    protected $db;
    /**
     * Latest inserted table name.
     */
    private ?string $last_insert_table_name = null;
    /**
     * Prepares the query against the database, and saves the connection
     * info necessary to execute the query later.
     *
     * NOTE: This version is based on SQL code. Child classes should
     * override this method.
     *
     * @param array $options Passed to the connection's prepare statement.
     *                       Unused in the OCI8 driver.
     */
    public function _prepare(string $sql, array $options = []): Prepared_Query
    {
        if (!$this->statement = oci_parse($this->db->conn_id, $this->parameterize($sql))) {
            $error = oci_error($this->db->conn_id);
            $this->error_code = $error['code'] ?? 0;
            $this->error_string = $error['message'] ?? '';
            if ($this->db->db_debug) {
                throw new Database_Exception($this->error_string . ' code: ' . $this->error_code);
            }
        }
        $this->last_insert_table_name = $this->db->parse_insert_table_name($sql);
        return $this;
    }
    /**
     * Takes a new set of data and runs it against the currently
     * prepared query. Upon success, will return a Results object.
     */
    public function _execute(array $data): bool
    {
        if (!isset($this->statement)) {
            throw new BadMethodCallException('You must call prepare before trying to execute a prepared statement.');
        }
        $binary_data = null;
        foreach (array_keys($data) as $key) {
            if (is_string($data[$key]) && $this->is_binary($data[$key])) {
                $binary_data = oci_new_descriptor($this->db->conn_id, OCI_D_LOB);
                $binary_data->write_temporary($data[$key], OCI_TEMP_BLOB);
                oci_bind_by_name($this->statement, ':' . $key, $binary_data, -1, OCI_B_BLOB);
            } else {
                oci_bind_by_name($this->statement, ':' . $key, $data[$key]);
            }
        }
        $result = oci_execute($this->statement, $this->db->commit_mode);
        if ($binary_data instanceof Oci_Lob) {
            $binary_data->free();
        }
        if ($result && $this->last_insert_table_name !== '') {
            $this->db->last_inserted_table_name = $this->last_insert_table_name;
        }
        return $result;
    }
    /**
     * Returns the statement resource for the prepared query or false when preparing failed.
     *
     * @return resource|null
     */
    public function _get_result()
    {
        return $this->statement;
    }
    /**
     * Deallocate prepared statements.
     */
    protected function _close(): bool
    {
        return oci_free_statement($this->statement);
    }
    /**
     * Replaces the ? placeholders with :0, :1, etc parameters for use
     * within the prepared query.
     */
    public function parameterize(string $sql): string
    {
        // Track our current value
        $count = 0;
        return preg_replace_callback('/\?/', static function ($matches) use (&$count): string {
            return ':' . $count++;
        }, $sql);
    }
}