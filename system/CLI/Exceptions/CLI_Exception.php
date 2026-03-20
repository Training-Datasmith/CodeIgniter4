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
namespace Code_Igniter\CLI\Exceptions;

use Code_Igniter\Exceptions\Debug_Traceable_Trait;
use Code_Igniter\Exceptions\RuntimeException;
/**
 * CLIException
 */
class Cli_Exception extends RuntimeException
{
    use Debug_Traceable_Trait;
    /**
     * Thrown when `$color` specified for `$type` is not within the
     * allowed list of colors.
     *
     * @return CLIException
     */
    public static function for_invalid_color(string $type, string $color)
    {
        return new static(lang('CLI.invalidColor', [$type, $color]));
    }
}