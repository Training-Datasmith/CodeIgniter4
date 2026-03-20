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
 * Exception thrown when there is an error with the test code.
 */
class Test_Exception extends LogicException
{
    use Debug_Traceable_Trait;
    /**
     * @return static
     */
    public static function for_invalid_mock_class(string $name)
    {
        return new static(lang('Test.invalidMockClass', [$name]));
    }
}