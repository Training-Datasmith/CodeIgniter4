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
namespace Code_Igniter\Database\Sq_Lite3;

use Code_Igniter\Database\Base_Prepared_Query;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Exceptions\BadMethodCallException;
use Exception;
use Sq_Lite3;
use Sq_Lite3result;
use Sq_Lite3stmt;
/**
 * Prepared query for SQLite3
 *
 * @extends BasePreparedQuery<SQLite3, SQLite3Stmt, SQLite3Result>
 */
class Prepared_Query extends Base_Prepared_Query
{
    /**
     * The SQLite3Result resource, or false.
     *
     * @var false|SQLite3Result
     */
    protected $result;
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
        if (!$this->statement = $this->db->conn_id->prepare($sql)) {
            $this->error_code = $this->db->conn_id->last_error_code();
            $this->error_string = $this->db->conn_id->last_error_msg();
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
        foreach ($data as $key => $item) {
            // Determine the type string
            if (is_int($item)) {
                $bind_type = SQLITE3_INTEGER;
            } elseif (is_float($item)) {
                $bind_type = SQLITE3_FLOAT;
            } elseif (is_string($item) && $this->is_binary($item)) {
                $bind_type = SQLITE3_BLOB;
            } else {
                $bind_type = SQLITE3_TEXT;
            }
            // Bind it
            $this->statement->bind_value($key + 1, $item, $bind_type);
        }
        try {
            $this->result = $this->statement->execute();
        } catch (Exception $e) {
            if ($this->db->db_debug) {
                throw new Database_Exception($e->get_message(), $e->get_code(), $e);
            }
            return false;
        }
        return $this->result !== false;
    }
    /**
     * Returns the result object for the prepared query or false on failure.
     *
     * @return false|SQLite3Result
     */
    public function _get_result()
    {
        return $this->result;
    }
    /**
     * Deallocate prepared statements.
     */
    protected function _close(): bool
    {
        return $this->statement->close();
    }
}