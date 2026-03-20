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
namespace Code_Igniter\Database\Exceptions;

use Code_Igniter\Exceptions\Has_Exit_Code_Interface;
use Code_Igniter\Exceptions\RuntimeException;
class Database_Exception extends RuntimeException implements Exception_Interface, Has_Exit_Code_Interface
{
    public function get_exit_code(): int
    {
        return EXIT_DATABASE;
    }
}