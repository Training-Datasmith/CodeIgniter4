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
class Timestamp_Cast extends Base_Cast
{
    public static function get($value, array $params = [])
    {
        $value = strtotime($value);
        if ($value === false) {
            throw Cast_Exception::for_invalid_timestamp();
        }
        return $value;
    }
}