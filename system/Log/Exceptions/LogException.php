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
namespace Code_Igniter\Log\Exceptions;

use Code_Igniter\Exceptions\Framework_Exception;
class Log_Exception extends Framework_Exception
{
    /**
     * @return static
     */
    public static function for_invalid_log_level(string $level)
    {
        return new static(lang('Log.invalidLogLevel', [$level]));
    }
    /**
     * @return static
     */
    public static function for_invalid_message_type(string $message_type)
    {
        return new static(lang('Log.invalidMessageType', [$message_type]));
    }
}