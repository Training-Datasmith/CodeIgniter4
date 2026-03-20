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
namespace Code_Igniter\Security\Exceptions;

use Code_Igniter\Exceptions\Framework_Exception;
use Code_Igniter\Exceptions\Http_Exception_Interface;
class Security_Exception extends Framework_Exception implements Http_Exception_Interface
{
    /**
     * Throws when some specific action is not allowed.
     * This is used for CSRF protection.
     *
     * @return static
     */
    public static function for_disallowed_action()
    {
        return new static(lang('Security.disallowedAction'), 403);
    }
    /**
     * Throws if a secure cookie is dispatched when the current connection is not
     * secure.
     */
    public static function for_insecure_cookie(): static
    {
        return new static(lang('Security.insecureCookie'));
    }
    /**
     * Throws when the source string contains invalid UTF-8 characters.
     *
     * @param string $source The source string
     * @param string $string The invalid string
     *
     * @return static
     */
    public static function for_invalid_utf8chars(string $source, string $string)
    {
        return new static('Invalid UTF-8 characters in ' . $source . ': ' . $string, 400);
    }
    /**
     * Throws when the source string contains invalid control characters.
     *
     * @param string $source The source string
     * @param string $string The invalid string
     *
     * @return static
     */
    public static function for_invalid_control_chars(string $source, string $string)
    {
        return new static('Invalid Control characters in ' . $source . ': ' . $string, 400);
    }
    /**
     * @deprecated Use `CookieException::forInvalidSameSite()` instead.
     *
     * @codeCoverageIgnore
     *
     * @return static
     */
    public static function for_invalid_same_site(string $samesite)
    {
        return new static(lang('Security.invalidSameSite', [$samesite]));
    }
}