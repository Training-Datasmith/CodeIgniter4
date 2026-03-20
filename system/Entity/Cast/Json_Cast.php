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

use Code_Igniter\Entity\Exceptions\Cast_Exception;
use Json_Exception;
use stdClass;
class Json_Cast extends Base_Cast
{
    public static function get($value, array $params = [])
    {
        $associative = in_array('array', $params, true);
        $tmp = $value !== null ? $associative ? [] : new stdClass() : null;
        if (function_exists('json_decode') && (is_string($value) && strlen($value) > 1 && in_array($value[0], ['[', '{', '"'], true) || is_numeric($value))) {
            try {
                $tmp = json_decode($value, $associative, 512, JSON_THROW_ON_ERROR);
            } catch (Json_Exception $e) {
                throw Cast_Exception::for_invalid_json_format($e->get_code());
            }
        }
        return $tmp;
    }
    /**
     * {@inheritDoc}
     */
    public static function set($value, array $params = []): string
    {
        if (function_exists('json_encode')) {
            try {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (Json_Exception $e) {
                throw Cast_Exception::for_invalid_json_format($e->get_code());
            }
        }
        return $value;
    }
}