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
namespace Code_Igniter\Data_Caster\Cast;

use Backed_Enum;
use Code_Igniter\Data_Caster\Exceptions\Cast_Exception;
use Reflection_Enum;
use Unit_Enum;
/**
 * Class EnumCast
 *
 * Handles casting for PHP enums (both backed and unit enums)
 *
 * (PHP) [enum --> value/name] --> (DB driver) --> (DB column) int|string
 *       [     <-- value/name] <-- (DB driver) <-- (DB column) int|string
 */
class Enum_Cast extends Base_Cast implements Cast_Interface
{
    public static function get(mixed $value, array $params = [], ?object $helper = null): Backed_Enum|Unit_Enum
    {
        if (!is_string($value) && !is_int($value)) {
            self::invalid_type_value_error($value);
        }
        $enum_class = $params[0] ?? null;
        if ($enum_class === null) {
            throw Cast_Exception::for_missing_enum_class();
        }
        if (!enum_exists($enum_class)) {
            throw Cast_Exception::for_not_enum($enum_class);
        }
        $reflection = new Reflection_Enum($enum_class);
        // Unit enum
        if (!$reflection->is_backed()) {
            // Unit enum - match by name
            foreach ($enum_class::cases() as $case) {
                if ($case->name === $value) {
                    return $case;
                }
            }
            throw Cast_Exception::for_invalid_enum_case_name($enum_class, $value);
        }
        // Backed enum - validate and cast the value to proper type
        $backing_type = $reflection->get_backing_type();
        // Cast to proper type (int or string)
        if ($backing_type->get_name() === 'int') {
            $value = (int) $value;
        } elseif ($backing_type->get_name() === 'string') {
            $value = (string) $value;
        }
        $enum = $enum_class::try_from($value);
        if ($enum === null) {
            throw Cast_Exception::for_invalid_enum_value($enum_class, $value);
        }
        return $enum;
    }
    public static function set(mixed $value, array $params = [], ?object $helper = null): int|string
    {
        if (!is_object($value) || !enum_exists($value::class)) {
            self::invalid_type_value_error($value);
        }
        // Get the expected enum class
        $enum_class = $params[0] ?? null;
        if ($enum_class === null) {
            throw Cast_Exception::for_missing_enum_class();
        }
        if (!enum_exists($enum_class)) {
            throw Cast_Exception::for_not_enum($enum_class);
        }
        // Validate that the enum is of the expected type
        if (!$value instanceof $enum_class) {
            throw Cast_Exception::for_invalid_enum_type($enum_class, $value::class);
        }
        $reflection = new Reflection_Enum($value::class);
        // Backed enum - return the properly typed backing value
        if ($reflection->is_backed()) {
            /** @var BackedEnum $value */
            return $value->value;
        }
        // Unit enum - return the case name
        /** @var UnitEnum $value */
        return $value->name;
    }
}