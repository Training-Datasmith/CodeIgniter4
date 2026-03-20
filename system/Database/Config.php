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

use Code_Igniter\Config\Base_Config;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Config\Database as DbConfig;
/**
 * @see \CodeIgniter\Database\ConfigTest
 */
class Config extends Base_Config
{
    /**
     * Cache for instance of any connections that
     * have been requested as a "shared" instance.
     *
     * @var array
     */
    protected static $instances = [];
    /**
     * The main instance used to manage all of
     * our open database connections.
     *
     * @var Database|null
     */
    protected static $factory;
    /**
     * Returns the database connection
     *
     * @param array|BaseConnection|non-empty-string|null $group     The name of the connection group to use,
     *                                                              or an array of configuration settings.
     * @param bool                                       $getShared Whether to return a shared instance of the connection.
     *
     * @return BaseConnection
     */
    public static function connect($group = null, bool $get_shared = true)
    {
        // If a DB connection is passed in, just pass it back
        if ($group instanceof Base_Connection) {
            return $group;
        }
        if (is_array($group)) {
            $config = $group;
            $group = 'custom-' . md5(json_encode($config));
        } else {
            $db_config = config(Db_Config::class);
            if ($group === null) {
                $group = ENVIRONMENT === 'testing' ? 'tests' : $db_config->default_group;
            }
            assert(is_string($group));
            if (!isset($db_config->{$group})) {
                throw new InvalidArgumentException('"' . $group . '" is not a valid database connection group.');
            }
            $config = $db_config->{$group};
        }
        if ($get_shared && isset(static::$instances[$group])) {
            return static::$instances[$group];
        }
        static::ensure_factory();
        $connection = static::$factory->load($config, $group);
        if ($get_shared) {
            static::$instances[$group] = $connection;
        }
        return $connection;
    }
    /**
     * Returns an array of all db connections currently made.
     */
    public static function get_connections(): array
    {
        return static::$instances;
    }
    /**
     * Loads and returns an instance of the Forge for the specified
     * database group, and loads the group if it hasn't been loaded yet.
     *
     * @param array|ConnectionInterface|string|null $group
     *
     * @return Forge
     */
    public static function forge($group = null)
    {
        $db = static::connect($group);
        return static::$factory->load_forge($db);
    }
    /**
     * Returns a new instance of the Database Utilities class.
     *
     * @param array|string|null $group
     *
     * @return BaseUtils
     */
    public static function utils($group = null)
    {
        $db = static::connect($group);
        return static::$factory->load_utils($db);
    }
    /**
     * Returns a new instance of the Database Seeder.
     *
     * @param non-empty-string|null $group
     *
     * @return Seeder
     */
    public static function seeder(?string $group = null)
    {
        $config = config(Db_Config::class);
        return new Seeder($config, static::connect($group));
    }
    /**
     * Ensures the database Connection Manager/Factory is loaded and ready to use.
     *
     * @return void
     */
    protected static function ensure_factory()
    {
        if (static::$factory instanceof Database) {
            return;
        }
        static::$factory = new Database();
    }
    /**
     * Reconnect database connections for worker mode at the start of a request.
     *
     * This should be called at the beginning of each request in worker mode,
     * before the application runs.
     */
    public static function reconnect_for_worker_mode(): void
    {
        foreach (static::$instances as $connection) {
            $connection->reconnect();
        }
    }
    /**
     * Cleanup database connections for worker mode.
     *
     * Rolls back any uncommitted transactions and resets transaction status
     * to ensure a clean state for the next request.
     *
     * Uncommitted transactions at this point indicate a bug in the
     * application code (transactions should be completed before request ends).
     *
     * Called at the END of each request to clean up state.
     */
    public static function cleanup_for_worker_mode(): void
    {
        foreach (static::$instances as $group => $connection) {
            if ($connection->trans_depth > 0) {
                log_message('error', "Uncommitted transaction detected in database group '{$group}'. Transactions must be completed before request ends.");
                while ($connection->trans_depth > 0) {
                    $connection->trans_rollback();
                }
            }
            $connection->reset_trans_status();
        }
    }
}