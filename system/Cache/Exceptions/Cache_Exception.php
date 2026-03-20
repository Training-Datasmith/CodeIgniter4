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
namespace Code_Igniter\Cache\Exceptions;

use Code_Igniter\Exceptions\Debug_Traceable_Trait;
use Code_Igniter\Exceptions\RuntimeException;
class Cache_Exception extends RuntimeException
{
    use Debug_Traceable_Trait;
    /**
     * Thrown when handler has no permission to write cache.
     *
     * @return static
     */
    public static function for_unable_to_write(string $path)
    {
        return new static(lang('Cache.unableToWrite', [$path]));
    }
    /**
     * Thrown when an unrecognized handler is used.
     *
     * @return static
     */
    public static function for_invalid_handlers()
    {
        return new static(lang('Cache.invalidHandlers'));
    }
    /**
     * Thrown when no backup handler is setup in config.
     *
     * @return static
     */
    public static function for_no_backup()
    {
        return new static(lang('Cache.noBackup'));
    }
    /**
     * Thrown when specified handler was not found.
     *
     * @return static
     */
    public static function for_handler_not_found()
    {
        return new static(lang('Cache.handlerNotFound'));
    }
}