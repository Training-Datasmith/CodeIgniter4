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
namespace Code_Igniter\Format\Exceptions;

use Code_Igniter\Exceptions\Debug_Traceable_Trait;
use Code_Igniter\Exceptions\RuntimeException;
/**
 * FormatException
 */
class Format_Exception extends RuntimeException
{
    use Debug_Traceable_Trait;
    /**
     * Thrown when the instantiated class does not exist.
     *
     * @return static
     */
    public static function for_invalid_formatter(string $class)
    {
        return new static(lang('Format.invalidFormatter', [$class]));
    }
    /**
     * Thrown in JSONFormatter when the json_encode produces
     * an error code other than JSON_ERROR_NONE and JSON_ERROR_RECURSION.
     *
     * @param string|null $error The error message
     *
     * @return static
     */
    public static function for_invalid_json(?string $error = null)
    {
        return new static(lang('Format.invalidJSON', [$error]));
    }
    /**
     * Thrown when the supplied MIME type has no
     * defined Formatter class.
     *
     * @return static
     */
    public static function for_invalid_mime(string $mime)
    {
        return new static(lang('Format.invalidMime', [$mime]));
    }
    /**
     * Thrown on XMLFormatter when the `simplexml` extension
     * is not installed.
     *
     * @return static
     *
     * @codeCoverageIgnore
     */
    public static function for_missing_extension()
    {
        return new static(lang('Format.missingExtension'));
    }
}