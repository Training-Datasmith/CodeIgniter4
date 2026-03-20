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
namespace Code_Igniter\Encryption\Exceptions;

use Code_Igniter\Exceptions\Debug_Traceable_Trait;
use Code_Igniter\Exceptions\RuntimeException;
/**
 * Encryption exception
 */
class Encryption_Exception extends RuntimeException
{
    use Debug_Traceable_Trait;
    /**
     * Thrown when no driver is present in the active encryption session.
     *
     * @return static
     */
    public static function for_no_driver_requested()
    {
        return new static(lang('Encryption.noDriverRequested'));
    }
    /**
     * Thrown when the handler requested is not available.
     *
     * @return static
     */
    public static function for_no_handler_available(string $handler)
    {
        return new static(lang('Encryption.noHandlerAvailable', [$handler]));
    }
    /**
     * Thrown when the handler requested is unknown.
     *
     * @return static
     */
    public static function for_un_known_handler(?string $driver = null)
    {
        return new static(lang('Encryption.unKnownHandler', [$driver]));
    }
    /**
     * Thrown when no starter key is provided for the current encryption session.
     *
     * @return static
     */
    public static function for_needs_starter_key()
    {
        return new static(lang('Encryption.starterKeyNeeded'));
    }
    /**
     * Thrown during data decryption when a problem or error occurred.
     *
     * @return static
     */
    public static function for_authentication_failed()
    {
        return new static(lang('Encryption.authenticationFailed'));
    }
    /**
     * Thrown during data encryption when a problem or error occurred.
     *
     * @return static
     */
    public static function for_encryption_failed()
    {
        return new static(lang('Encryption.encryptionFailed'));
    }
}