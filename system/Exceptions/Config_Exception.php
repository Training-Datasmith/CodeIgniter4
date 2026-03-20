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
namespace Code_Igniter\Exceptions;

/**
 * Exception thrown if the value of the Config class is invalid or the type is
 * incorrect.
 */
class Config_Exception extends RuntimeException implements Has_Exit_Code_Interface
{
    use Debug_Traceable_Trait;
    public function get_exit_code(): int
    {
        return EXIT_CONFIG;
    }
    /**
     * @return static
     */
    public static function for_disabled_migrations()
    {
        return new static(lang('Migrations.disabled'));
    }
}