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

use Argument_Count_Error;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Events\Events;
use Code_Igniter\Exceptions\BadMethodCallException;
use ErrorException;
/**
 * @template TConnection
 * @template TStatement
 * @template TResult
 *
 * @implements PreparedQueryInterface<TConnection, TStatement, TResult>
 */
abstract class Base_Prepared_Query implements Prepared_Query_Interface
{
    /**
     * The prepared statement itself.
     *
     * @var TStatement|null
     */
    protected $statement;
    /**
     * The error code, if any.
     *
     * @var int
     */
    protected $error_code;
    /**
     * The error message, if any.
     *
     * @var string
     */
    protected $error_string;
    /**
     * Holds the prepared query object
     * that is cloned during execute.
     *
     * @var Query
     */
    protected $query;
    /**
     * A reference to the db connection to use.
     *
     * @var BaseConnection<TConnection, TResult>
     */
    protected $db;
    public function __construct(Base_Connection $db)
    {
        $this->db = $db;
    }
    /**
     * Prepares the query against the database, and saves the connection
     * info necessary to execute the query later.
     *
     * NOTE: This version is based on SQL code. Child classes should
     * override this method.
     *
     * @return $this
     */
    public function prepare(string $sql, array $options = [], string $query_class = Query::class)
    {
        // We only support positional placeholders (?), so convert
        // named placeholders (:name or :name:) while leaving dialect
        // syntax like PostgreSQL casts (::type) untouched.
        $sql = preg_replace('/(?<!:):([a-zA-Z_]\w*):?(?!:)/', '?', $sql);
        /** @var Query $query */
        $query = new $query_class($this->db);
        $query->set_query($sql);
        if (!empty($this->db->swap_pre) && !empty($this->db->db_prefix)) {
            $query->swap_prefix($this->db->db_prefix, $this->db->swap_pre);
        }
        $this->query = $query;
        return $this->_prepare($query->get_original_query(), $options);
    }
    /**
     * The database-dependent portion of the prepare statement.
     *
     * @return $this
     */
    abstract public function _prepare(string $sql, array $options = []);
    /**
     * Takes a new set of data and runs it against the currently
     * prepared query. Upon success, will return a Results object.
     *
     * @return bool|ResultInterface<TConnection, TResult>
     *
     * @throws DatabaseException
     */
    public function execute(...$data)
    {
        // Execute the Query.
        $start_time = microtime(true);
        try {
            $exception = null;
            $result = $this->_execute($data);
        } catch (Argument_Count_Error|ErrorException $exception) {
            $result = false;
        }
        // Update our query object
        $query = clone $this->query;
        $query->set_binds($data);
        if ($result === false) {
            $query->set_duration($start_time, $start_time);
            // This will trigger a rollback if transactions are being used
            $this->db->handle_trans_status();
            if ($this->db->db_debug) {
                // We call this function in order to roll-back queries
                // if transactions are enabled. If we don't call this here
                // the error message will trigger an exit, causing the
                // transactions to remain in limbo.
                while ($this->db->trans_depth !== 0) {
                    $trans_depth = $this->db->trans_depth;
                    $this->db->trans_complete();
                    if ($trans_depth === $this->db->trans_depth) {
                        log_message('error', 'Database: Failure during an automated transaction commit/rollback!');
                        break;
                    }
                }
                // Let others do something with this query.
                Events::trigger('DBQuery', $query);
                if ($exception !== null) {
                    throw new Database_Exception($exception->get_message(), $exception->get_code(), $exception);
                }
                return false;
            }
            // Let others do something with this query.
            Events::trigger('DBQuery', $query);
            return false;
        }
        $query->set_duration($start_time);
        // Let others do something with this query
        Events::trigger('DBQuery', $query);
        if ($this->db->is_write_type((string) $query)) {
            return true;
        }
        // Return a result object
        $result_class = str_replace('PreparedQuery', 'Result', static::class);
        $result_id = $this->_get_result();
        return new $result_class($this->db->conn_id, $result_id);
    }
    /**
     * The database dependant version of the execute method.
     */
    abstract public function _execute(array $data): bool;
    /**
     * Returns the result object for the prepared query.
     *
     * @return object|resource|null
     */
    abstract public function _get_result();
    /**
     * Explicitly closes the prepared statement.
     *
     * @throws BadMethodCallException
     */
    public function close(): bool
    {
        if (!isset($this->statement)) {
            throw new BadMethodCallException('Cannot call close on a non-existing prepared statement.');
        }
        try {
            return $this->_close();
        } finally {
            $this->statement = null;
        }
    }
    /**
     * The database-dependent version of the close method.
     */
    abstract protected function _close(): bool;
    /**
     * Returns the SQL that has been prepared.
     */
    public function get_query_string(): string
    {
        if (!$this->query instanceof Query_Interface) {
            throw new BadMethodCallException('Cannot call getQueryString on a prepared query until after the query has been prepared.');
        }
        return $this->query->get_query();
    }
    /**
     * A helper to determine if any error exists.
     */
    public function has_error(): bool
    {
        return !empty($this->error_string);
    }
    /**
     * Returns the error code created while executing this statement.
     */
    public function get_error_code(): int
    {
        return $this->error_code;
    }
    /**
     * Returns the error message created while executing this statement.
     */
    public function get_error_message(): string
    {
        return $this->error_string;
    }
    /**
     * Whether the input contain binary data.
     */
    protected function is_binary(string $input): bool
    {
        return mb_detect_encoding($input, 'UTF-8', true) === false;
    }
}