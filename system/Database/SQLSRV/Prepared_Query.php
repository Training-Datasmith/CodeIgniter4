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

use Code_Igniter\Database\Base_Prepared_Query;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Exceptions\BadMethodCallException;
/**
 * Prepared query for Postgre
 *
 * @extends BasePreparedQuery<resource, resource, resource>
 */
class Prepared_Query extends Base_Prepared_Query
{
    /**
     * Parameters array used to store the dynamic variables.
     *
     * @var array
     */
    protected $parameters = [];
    /**
     * A reference to the db connection to use.
     *
     * @var Connection
     */
    protected $db;
    public function __construct(Connection $db)
    {
        parent::__construct($db);
    }
    /**
     * Prepares the query against the database, and saves the connection
     * info necessary to execute the query later.
     *
     * NOTE: This version is based on SQL code. Child classes should
     * override this method.
     *
     * @param array $options Options takes an associative array;
     *
     * @throws DatabaseException
     */
    public function _prepare(string $sql, array $options = []): Prepared_Query
    {
        // Prepare parameters for the query
        $query_string = $this->get_query_string();
        $parameters = $this->parameterize($query_string, $options);
        // Prepare the query
        $this->statement = sqlsrv_prepare($this->db->conn_id, $sql, $parameters);
        if (!$this->statement) {
            if ($this->db->db_debug) {
                throw new Database_Exception($this->db->get_all_error_messages());
            }
            $info = $this->db->error();
            $this->error_code = $info['code'];
            $this->error_string = $info['message'];
        }
        return $this;
    }
    /**
     * Takes a new set of data and runs it against the currently
     * prepared query.
     */
    public function _execute(array $data): bool
    {
        if (!isset($this->statement)) {
            throw new BadMethodCallException('You must call prepare before trying to execute a prepared statement.');
        }
        foreach ($data as $key => $value) {
            $this->parameters[$key] = $value;
        }
        $result = sqlsrv_execute($this->statement);
        if ($result === false && $this->db->db_debug) {
            throw new Database_Exception($this->db->get_all_error_messages());
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
        return sqlsrv_free_stmt($this->statement);
    }
    /**
     * Handle parameters.
     *
     * @param array<int, mixed> $options
     */
    protected function parameterize(string $query_string, array $options): array
    {
        $number_of_variables = substr_count($query_string, '?');
        $params = [];
        for ($c = 0; $c < $number_of_variables; $c++) {
            $this->parameters[$c] = null;
            if (isset($options[$c])) {
                $params[] = [&$this->parameters[$c], SQLSRV_PARAM_IN, $options[$c]];
            } else {
                $params[] =& $this->parameters[$c];
            }
        }
        return $params;
    }
}