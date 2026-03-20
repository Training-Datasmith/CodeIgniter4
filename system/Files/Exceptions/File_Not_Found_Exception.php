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
class File_Not_Found_Exception extends RuntimeException implements Exception_Interface
{
    use Debug_Traceable_Trait;
    /**
     * @return static
     */
    public static function for_file_not_found(string $path)
    {
        return new static(lang('Files.fileNotFound', [$path]));
    }
}