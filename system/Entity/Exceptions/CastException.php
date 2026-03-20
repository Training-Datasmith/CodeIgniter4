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
namespace Code_Igniter\Entity\Exceptions;

use Code_Igniter\Exceptions\Framework_Exception;
use Code_Igniter\Exceptions\Has_Exit_Code_Interface;
/**
 * CastException is thrown for invalid cast initialization and management.
 */
class Cast_Exception extends Framework_Exception implements Has_Exit_Code_Interface
{
    public function get_exit_code(): int
    {
        return EXIT_CONFIG;
    }
    /**
     * Thrown when the cast class does not extends BaseCast.
     *
     * @return static
     */
    public static function for_invalid_interface(string $class)
    {
        return new static(lang('Cast.baseCastMissing', [$class]));
    }
    /**
     * Thrown when the Json format is invalid.
     *
     * @return static
     */
    public static function for_invalid_json_format(int $error)
    {
        return match ($error) {
            JSON_ERROR_DEPTH => new static(lang('Cast.jsonErrorDepth')),
            JSON_ERROR_STATE_MISMATCH => new static(lang('Cast.jsonErrorStateMismatch')),
            JSON_ERROR_CTRL_CHAR => new static(lang('Cast.jsonErrorCtrlChar')),
            JSON_ERROR_SYNTAX => new static(lang('Cast.jsonErrorSyntax')),
            JSON_ERROR_UTF8 => new static(lang('Cast.jsonErrorUtf8')),
            default => new static(lang('Cast.jsonErrorUnknown')),
        };
    }
    /**
     * Thrown when the cast method is not `get` or `set`.
     *
     * @return static
     */
    public static function for_invalid_method(string $method)
    {
        return new static(lang('Cast.invalidCastMethod', [$method]));
    }
    /**
     * Thrown when the casting timestamp is not correct timestamp.
     *
     * @return static
     */
    public static function for_invalid_timestamp()
    {
        return new static(lang('Cast.invalidTimestamp'));
    }
    /**
     * Thrown when the enum class is not specified in cast parameters.
     *
     * @return static
     */
    public static function for_missing_enum_class()
    {
        return new static(lang('Cast.enumMissingClass'));
    }
    /**
     * Thrown when the specified class is not an enum.
     *
     * @return static
     */
    public static function for_not_enum(string $class)
    {
        return new static(lang('Cast.enumNotEnum', [$class]));
    }
    /**
     * Thrown when an invalid value is provided for an enum.
     *
     * @return static
     */
    public static function for_invalid_enum_value(string $enum_class, mixed $value)
    {
        return new static(lang('Cast.enumInvalidValue', [$enum_class, $value]));
    }
    /**
     * Thrown when an invalid case name is provided for a unit enum.
     *
     * @return static
     */
    public static function for_invalid_enum_case_name(string $enum_class, string $case_name)
    {
        return new static(lang('Cast.enumInvalidCaseName', [$case_name, $enum_class]));
    }
    /**
     * Thrown when an enum instance of wrong type is provided.
     *
     * @return static
     */
    public static function for_invalid_enum_type(string $expected_class, string $actual_class)
    {
        return new static(lang('Cast.enumInvalidType', [$actual_class, $expected_class]));
    }
}