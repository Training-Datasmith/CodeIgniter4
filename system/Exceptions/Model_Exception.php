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
 * Model Exceptions.
 */
class Model_Exception extends Framework_Exception
{
    /**
     * @return static
     */
    public static function for_no_primary_key(string $model_name)
    {
        return new static(lang('Database.noPrimaryKey', [$model_name]));
    }
    /**
     * @return static
     */
    public static function for_no_date_format(string $model_name)
    {
        return new static(lang('Database.noDateFormat', [$model_name]));
    }
    /**
     * @return static
     */
    public static function for_method_not_available(string $model_name, string $method_name)
    {
        return new static(lang('Database.methodNotAvailable', [$model_name, $method_name]));
    }
}