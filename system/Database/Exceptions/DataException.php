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

use Code_Igniter\Exceptions\Debug_Traceable_Trait;
use Code_Igniter\Exceptions\RuntimeException;
class Data_Exception extends RuntimeException implements Exception_Interface
{
    use Debug_Traceable_Trait;
    /**
     * Used by the Model's trigger() method when the callback cannot be found.
     *
     * @return DataException
     */
    public static function for_invalid_method_triggered(string $method)
    {
        return new static(lang('Database.invalidEvent', [$method]));
    }
    /**
     * Used by Model's insert/update methods when there isn't
     * any data to actually work with.
     *
     * @return DataException
     */
    public static function for_empty_dataset(string $mode)
    {
        return new static(lang('Database.emptyDataset', [$mode]));
    }
    /**
     * Used by Model's insert/update methods when there is no
     * primary key defined and Model has option `useAutoIncrement`
     * set to false.
     *
     * @return DataException
     */
    public static function for_empty_primary_key(string $mode)
    {
        return new static(lang('Database.emptyPrimaryKey', [$mode]));
    }
    /**
     * Thrown when an argument for one of the Model's methods
     * were empty or otherwise invalid, and they could not be
     * to work correctly for that method.
     *
     * @return DataException
     */
    public static function for_invalid_argument(string $argument)
    {
        return new static(lang('Database.invalidArgument', [$argument]));
    }
    /**
     * @return DataException
     */
    public static function for_invalid_allowed_fields(string $model)
    {
        return new static(lang('Database.invalidAllowedFields', [$model]));
    }
    /**
     * @return DataException
     */
    public static function for_table_not_found(string $table)
    {
        return new static(lang('Database.tableNotFound', [$table]));
    }
    /**
     * @return DataException
     */
    public static function for_empty_input_given(string $argument)
    {
        return new static(lang('Database.forEmptyInputGiven', [$argument]));
    }
    /**
     * @return DataException
     */
    public static function for_find_column_have_multiple_columns()
    {
        return new static(lang('Database.forFindColumnHaveMultipleColumns'));
    }
}