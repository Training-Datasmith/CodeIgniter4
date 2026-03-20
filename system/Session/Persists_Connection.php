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
namespace Code_Igniter\Session;

/**
 * Trait for session handlers that need persistent connections.
 */
trait Persists_Connection
{
    /**
     * Connection pool keyed by connection identifier.
     * Allows multiple configurations to each have their own connection.
     *
     * @var array<string, object>
     */
    protected static $connection_pool = [];
    /**
     * Get connection identifier based on configuration.
     * This returns a unique hash for each distinct connection configuration.
     */
    protected function get_connection_identifier(): string
    {
        return hash('xxh128', serialize(['class' => static::class, 'savePath' => $this->save_path, 'keyPrefix' => $this->key_prefix]));
    }
    /**
     * Check if a persistent connection exists for this configuration.
     */
    protected function has_persistent_connection(): bool
    {
        $identifier = $this->get_connection_identifier();
        return isset(self::$connection_pool[$identifier]);
    }
    /**
     * Get the persistent connection for this configuration.
     */
    protected function get_persistent_connection(): ?object
    {
        $identifier = $this->get_connection_identifier();
        return self::$connection_pool[$identifier] ?? null;
    }
    /**
     * Store a connection for persistence.
     *
     * @param object|null $connection The connection to persist (null to clear).
     */
    protected function set_persistent_connection(?object $connection): void
    {
        $identifier = $this->get_connection_identifier();
        if ($connection === null) {
            unset(self::$connection_pool[$identifier]);
        } else {
            self::$connection_pool[$identifier] = $connection;
        }
    }
    /**
     * Reset all persistent connections (useful for testing).
     */
    public static function reset_persistent_connections(): void
    {
        self::$connection_pool = [];
    }
}