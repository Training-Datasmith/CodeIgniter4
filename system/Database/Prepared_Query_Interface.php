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

use Code_Igniter\Exceptions\BadMethodCallException;
/**
 * @template TConnection
 * @template TStatement
 * @template TResult
 */
interface Prepared_Query_Interface
{
    /**
     * Takes a new set of data and runs it against the currently
     * prepared query. Upon success, will return a Results object.
     *
     * @return bool|ResultInterface<TConnection, TResult>
     */
    public function execute(...$data);
    /**
     * Prepares the query against the database, and saves the connection
     * info necessary to execute the query later.
     *
     * @return $this
     */
    public function prepare(string $sql, array $options = []);
    /**
     * Explicity closes the statement.
     *
     * @throws BadMethodCallException
     */
    public function close(): bool;
    /**
     * Returns the SQL that has been prepared.
     */
    public function get_query_string(): string;
    /**
     * Returns the error code created while executing this statement.
     */
    public function get_error_code(): int;
    /**
     * Returns the error message created while executing this statement.
     */
    public function get_error_message(): string;
}