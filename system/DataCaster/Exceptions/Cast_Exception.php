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
namespace Code_Igniter\Data_Caster\Exceptions;

use Code_Igniter\Entity\Exceptions\Cast_Exception as EntityCastException;
/**
 * CastException is thrown for invalid cast initialization and management.
 */
class Cast_Exception extends Entity_Cast_Exception
{
}