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

use Code_Igniter\Database\Base_Prepared_Query;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Exceptions\BadMethodCallException;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use mysqli_stmt;
/**
 * Prepared query for MySQLi
 *
 * @extends BasePreparedQuery<mysqli, mysqli_stmt, mysqli_result>
 */
class Prepared_Query extends Base_Prepared_Query
{
    /**
     * Prepares the query against the database, and saves the connection
     * info necessary to execute the query later.
     *
     * NOTE: This version is based on SQL code. Child classes should
     * override this method.
     *
     * @param array $options Passed to the connection's prepare statement.
     *                       Unused in the MySQLi driver.
     */
    public function _prepare(string $sql, array $options = []): Prepared_Query
    {
        // Mysqli driver doesn't like statements
        // with terminating semicolons.
        $sql = rtrim($sql, ';');
        if (!$this->statement = $this->db->mysqli->prepare($sql)) {
            $this->error_code = $this->db->mysqli->errno;
            $this->error_string = $this->db->mysqli->error;
            if ($this->db->db_debug) {
                throw new Database_Exception($this->error_string . ' code: ' . $this->error_code);
            }
        }
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
        // First off - bind the parameters
        $bind_types = '';
        $binary_data = [];
        // Determine the type string
        foreach ($data as $key => $item) {
            if (is_int($item)) {
                $bind_types .= 'i';
            } elseif (is_numeric($item)) {
                $bind_types .= 'd';
            } elseif (is_string($item) && $this->is_binary($item)) {
                $bind_types .= 'b';
                $binary_data[$key] = $item;
            } else {
                $bind_types .= 's';
            }
        }
        // Bind it
        $this->statement->bind_param($bind_types, ...$data);
        // Stream binary data
        foreach ($binary_data as $key => $value) {
            $this->statement->send_long_data($key, $value);
        }
        try {
            return $this->statement->execute();
        } catch (mysqli_sql_exception $e) {
            if ($this->db->db_debug) {
                throw new Database_Exception($e->get_message(), $e->get_code(), $e);
            }
            return false;
        }
    }
    /**
     * Returns the result object for the prepared query or false on failure.
     *
     * @return false|mysqli_result
     */
    public function _get_result()
    {
        return $this->statement->get_result();
    }
    /**
     * Deallocate prepared statements.
     */
    protected function _close(): bool
    {
        return $this->statement->close();
    }
}