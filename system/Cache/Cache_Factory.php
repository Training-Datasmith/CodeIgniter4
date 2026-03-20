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
namespace Code_Igniter\Cache;

use Code_Igniter\Cache\Exceptions\Cache_Exception;
use Code_Igniter\Exceptions\Critical_Error;
use Code_Igniter\Test\Mock\Mock_Cache;
use Config\Cache;
/**
 * A factory for loading the desired
 *
 * @see \CodeIgniter\Cache\CacheFactoryTest
 */
class Cache_Factory
{
    /**
     * The class to use when mocking
     *
     * @var string
     */
    public static $mock_class = Mock_Cache::class;
    /**
     * The service to inject the mock as
     *
     * @var string
     */
    public static $mock_service_name = 'cache';
    /**
     * Attempts to create the desired cache handler, based upon the
     *
     * @param non-empty-string|null $handler
     * @param non-empty-string|null $backup
     *
     * @return CacheInterface
     */
    public static function get_handler(Cache $config, ?string $handler = null, ?string $backup = null)
    {
        if (!isset($config->valid_handlers) || $config->valid_handlers === []) {
            throw Cache_Exception::for_invalid_handlers();
        }
        if (!isset($config->handler) || !isset($config->backup_handler)) {
            throw Cache_Exception::for_no_backup();
        }
        $handler ??= $config->handler;
        $backup ??= $config->backup_handler;
        if (!array_key_exists($handler, $config->valid_handlers) || !array_key_exists($backup, $config->valid_handlers)) {
            throw Cache_Exception::for_handler_not_found();
        }
        $adapter = new $config->valid_handlers[$handler]($config);
        if (!$adapter->is_supported()) {
            $adapter = new $config->valid_handlers[$backup]($config);
            if (!$adapter->is_supported()) {
                // Fall back to the dummy adapter.
                $adapter = new $config->valid_handlers['dummy']();
            }
        }
        // If $adapter->initialize() throws a CriticalError exception, we will attempt to
        // use the $backup handler, if that also fails, we resort to the dummy handler.
        try {
            $adapter->initialize();
        } catch (Critical_Error $e) {
            log_message('critical', $e . ' Resorting to using ' . $backup . ' handler.');
            // get the next best cache handler (or dummy if the $backup also fails)
            $adapter = self::get_handler($config, $backup, 'dummy');
        }
        return $adapter;
    }
}