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

/**
 * Represents a single statement that can be executed against the database.
 * Statements are platform-specific and can handle binding of binds.
 */
interface Query_Interface
{
    /**
     * Sets the raw query string to use for this statement.
     */
    public function set_query(string $sql, mixed $binds = null, bool $set_escape = true): self;
    /**
     * Returns the final, processed query string after binding, etal
     * has been performed.
     */
    public function get_query(): string;
    /**
     * Records the execution time of the statement using microtime(true)
     * for it's start and end values. If no end value is present, will
     * use the current time to determine total duration.
     */
    public function set_duration(float $start, ?float $end = null): self;
    /**
     * Returns the duration of this query during execution, or null if
     * the query has not been executed yet.
     *
     * @param int $decimals The accuracy of the returned time.
     */
    public function get_duration(int $decimals = 6): string;
    /**
     * Stores the error description that happened for this query.
     */
    public function set_error(int $code, string $error): self;
    /**
     * Reports whether this statement created an error not.
     */
    public function has_error(): bool;
    /**
     * Returns the error code created while executing this statement.
     */
    public function get_error_code(): int;
    /**
     * Returns the error message created while executing this statement.
     */
    public function get_error_message(): string;
    /**
     * Determines if the statement is a write-type query or not.
     */
    public function is_write_type(): bool;
    /**
     * Swaps out one table prefix for a new one.
     */
    public function swap_prefix(string $orig, string $swap): self;
    /**
     * Returns the original SQL that was passed into the system.
     */
    public function get_original_query(): string;
}