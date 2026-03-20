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
namespace Code_Igniter\HTTP\Exceptions;

use Code_Igniter\Exceptions\Http_Exception_Interface;
use Code_Igniter\Exceptions\RuntimeException;
/**
 * 400 Bad Request
 */
class Bad_Request_Exception extends RuntimeException implements Http_Exception_Interface
{
    /**
     * HTTP status code for Bad Request
     *
     * @var int
     */
    protected $code = 400;
    // @phpstan-ignore-line
}