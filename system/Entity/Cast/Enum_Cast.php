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
namespace Code_Igniter\Entity\Cast;

use Backed_Enum;
use Code_Igniter\Entity\Exceptions\Cast_Exception;
use Reflection_Enum;
use Unit_Enum;
class Enum_Cast extends Base_Cast
{
    public static function get($value, array $params = []): Backed_Enum|Unit_Enum
    {
        $enum_class = $params[0] ?? null;
        if ($enum_class === null) {
            throw Cast_Exception::for_missing_enum_class();
        }
        if (!enum_exists($enum_class)) {
            throw Cast_Exception::for_not_enum($enum_class);
        }
        $reflection = new Reflection_Enum($enum_class);
        // Backed enum - validate and cast the value to proper type
        if ($reflection->is_backed()) {
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
        // Unit enum - match by name
        foreach ($enum_class::cases() as $case) {
            if ($case->name === $value) {
                return $case;
            }
        }
        throw Cast_Exception::for_invalid_enum_case_name($enum_class, $value);
    }
    public static function set($value, array $params = []): int|string
    {
        // Get the expected enum class
        $enum_class = $params[0] ?? null;
        if ($enum_class === null) {
            throw Cast_Exception::for_missing_enum_class();
        }
        if (!enum_exists($enum_class)) {
            throw Cast_Exception::for_not_enum($enum_class);
        }
        // If it's already an enum object, validate and extract its value
        if (is_object($value) && enum_exists($value::class)) {
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
        $reflection = new Reflection_Enum($enum_class);
        // Validate backed enum values
        if ($reflection->is_backed()) {
            $backing_type = $reflection->get_backing_type();
            // Cast to proper type (int or string)
            if ($backing_type->get_name() === 'int') {
                $value = (int) $value;
            } elseif ($backing_type->get_name() === 'string') {
                $value = (string) $value;
            }
            if ($enum_class::try_from($value) === null) {
                throw Cast_Exception::for_invalid_enum_value($enum_class, $value);
            }
            return $value;
        }
        // Validate unit enum case names - must be a string
        $value = (string) $value;
        foreach ($enum_class::cases() as $case) {
            if ($case->name === $value) {
                return $value;
            }
        }
        throw Cast_Exception::for_invalid_enum_case_name($enum_class, $value);
    }
}