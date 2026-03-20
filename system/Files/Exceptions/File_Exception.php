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
namespace Code_Igniter\Files\Exceptions;

use Code_Igniter\Exceptions\Debug_Traceable_Trait;
use Code_Igniter\Exceptions\RuntimeException;
class File_Exception extends RuntimeException implements Exception_Interface
{
    use Debug_Traceable_Trait;
    /**
     * @return static
     */
    public static function for_unable_to_move(?string $from = null, ?string $to = null, ?string $error = null)
    {
        return new static(lang('Files.cannotMove', [$from, $to, $error]));
    }
    /**
     * Throws when an item is expected to be a directory but is not or is missing.
     *
     * @param string $caller The method causing the exception
     *
     * @return static
     */
    public static function for_expected_directory(string $caller)
    {
        return new static(lang('Files.expectedDirectory', [$caller]));
    }
    /**
     * Throws when an item is expected to be a file but is not or is missing.
     *
     * @param string $caller The method causing the exception
     *
     * @return static
     */
    public static function for_expected_file(string $caller)
    {
        return new static(lang('Files.expectedFile', [$caller]));
    }
}