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

use Code_Igniter\Database\Base_Prepared_Query;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Exceptions\BadMethodCallException;
use Exception;
use Pg_Sql\Connection as PgSqlConnection;
use Pg_Sql\Result as PgSqlResult;
/**
 * Prepared query for Postgre
 *
 * @extends BasePreparedQuery<PgSqlConnection, PgSqlResult, PgSqlResult>
 */
class Prepared_Query extends Base_Prepared_Query
{
    /**
     * Stores the name this query can be
     * used under by postgres. Only used internally.
     *
     * @var string
     */
    protected $name;
    /**
     * The result resource from a successful
     * pg_exec. Or false.
     *
     * @var false|PgSqlResult
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
     *
     * @throws Exception
     */
    public function _prepare(string $sql, array $options = []): Prepared_Query
    {
        $this->name = (string) random_int(1, 10000000000000000);
        $sql = $this->parameterize($sql);
        // Update the query object since the parameters are slightly different
        // than what was put in.
        $this->query->set_query($sql);
        if (!$this->statement = pg_prepare($this->db->conn_id, $this->name, $sql)) {
            $this->error_code = 0;
            $this->error_string = pg_last_error($this->db->conn_id);
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
        foreach ($data as &$item) {
            if (is_string($item) && $this->is_binary($item)) {
                $item = pg_escape_bytea($this->db->conn_id, $item);
            }
        }
        $this->result = pg_execute($this->db->conn_id, $this->name, $data);
        return (bool) $this->result;
    }
    /**
     * Returns the result object for the prepared query or false on failure.
     *
     * @return PgSqlResult|null
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
        return pg_query($this->db->conn_id, 'DEALLOCATE "' . $this->db->escape_identifiers($this->name) . '"') !== false;
    }
    /**
     * Replaces the ? placeholders with $1, $2, etc parameters for use
     * within the prepared query.
     */
    public function parameterize(string $sql): string
    {
        // Track our current value
        $count = 0;
        return preg_replace_callback('/\?/', static function () use (&$count): string {
            $count++;
            return "\${$count}";
        }, $sql);
    }
}