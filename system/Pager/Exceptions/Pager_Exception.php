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
namespace Code_Igniter\Pager\Exceptions;

use Code_Igniter\Exceptions\Framework_Exception;
class Pager_Exception extends Framework_Exception
{
    /**
     * Throws when the template is invalid.
     *
     * @return static
     */
    public static function for_invalid_template(?string $template = null)
    {
        return new static(lang('Pager.invalidTemplate', [$template]));
    }
    /**
     * Throws when the group is invalid.
     *
     * @return static
     */
    public static function for_invalid_pagination_group(?string $group = null)
    {
        return new static(lang('Pager.invalidPaginationGroup', [$group]));
    }
}