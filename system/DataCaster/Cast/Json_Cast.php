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

use Code_Igniter\Data_Caster\Exceptions\Cast_Exception;
use Json_Exception;
use stdClass;
/**
 * Class JsonCast
 *
 * (PHP) [array|stdClass --> string] --> (DB driver) --> (DB column) string
 *       [               <-- string] <-- (DB driver) <-- (DB column) string
 */
class Json_Cast extends Base_Cast
{
    public static function get(mixed $value, array $params = [], ?object $helper = null): array|stdClass
    {
        if (!is_string($value)) {
            self::invalid_type_value_error($value);
        }
        $associative = in_array('array', $params, true);
        $output = $associative ? [] : new stdClass();
        try {
            $output = json_decode($value, $associative, 512, JSON_THROW_ON_ERROR);
        } catch (Json_Exception $e) {
            throw Cast_Exception::for_invalid_json_format($e->get_code());
        }
        return $output;
    }
    public static function set(mixed $value, array $params = [], ?object $helper = null): string
    {
        try {
            $output = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Json_Exception $e) {
            throw Cast_Exception::for_invalid_json_format($e->get_code());
        }
        return $output;
    }
}