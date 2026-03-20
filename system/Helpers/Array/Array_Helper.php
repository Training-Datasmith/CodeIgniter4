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
namespace Code_Igniter\Helpers\Array;

use Code_Igniter\Exceptions\InvalidArgumentException;
/**
 * @interal This is internal implementation for the framework.
 *
 * If there are any methods that should be provided, make them
 * public APIs via helper functions.
 *
 * @see \CodeIgniter\Helpers\Array\ArrayHelperDotKeyExistsTest
 * @see \CodeIgniter\Helpers\Array\ArrayHelperRecursiveDiffTest
 * @see \CodeIgniter\Helpers\Array\ArrayHelperSortValuesByNaturalTest
 */
final class Array_Helper
{
    /**
     * Searches an array through dot syntax. Supports wildcard searches,
     * like `foo.*.bar`.
     *
     * @used-by dot_array_search()
     *
     * @param string $index The index as dot array syntax.
     *
     * @return array|bool|int|object|string|null
     */
    public static function dot_search(string $index, array $array)
    {
        return self::array_search_dot(self::convert_to_array($index), $array);
    }
    /**
     * @param string $index The index as dot array syntax.
     *
     * @return list<string> The index as an array.
     */
    private static function convert_to_array(string $index): array
    {
        // See https://regex101.com/r/44Ipql/1
        $segments = preg_split('/(?<!\\\\)\./', rtrim($index, '* '), 0, PREG_SPLIT_NO_EMPTY);
        return array_map(static fn($key): string => str_replace('\.', '.', $key), $segments);
    }
    /**
     * Recursively search the array with wildcards.
     *
     * @used-by dotSearch()
     *
     * @return array|bool|float|int|object|string|null
     */
    private static function array_search_dot(array $indexes, array $array)
    {
        // If index is empty, returns null.
        if ($indexes === []) {
            return null;
        }
        // Grab the current index
        $current_index = array_shift($indexes);
        if (!isset($array[$current_index]) && $current_index !== '*') {
            return null;
        }
        // Handle Wildcard (*)
        if ($current_index === '*') {
            $answer = [];
            foreach ($array as $value) {
                if (!is_array($value)) {
                    return null;
                }
                $answer[] = self::array_search_dot($indexes, $value);
            }
            $answer = array_filter($answer, static fn($value): bool => $value !== null);
            if ($answer !== []) {
                // If array only has one element, we return that element for BC.
                return count($answer) === 1 ? current($answer) : $answer;
            }
            return null;
        }
        // If this is the last index, make sure to return it now,
        // and not try to recurse through things.
        if ($indexes === []) {
            return $array[$current_index];
        }
        // Do we need to recursively search this value?
        if (is_array($array[$current_index]) && $array[$current_index] !== []) {
            return self::array_search_dot($indexes, $array[$current_index]);
        }
        // Otherwise, not found.
        return null;
    }
    /**
     * array_key_exists() with dot array syntax.
     *
     * If wildcard `*` is used, all items for the key after it must have the key.
     */
    public static function dot_key_exists(string $index, array $array): bool
    {
        if (str_ends_with($index, '*') || str_contains($index, '*.*')) {
            throw new InvalidArgumentException('You must set key right after "*". Invalid index: "' . $index . '"');
        }
        $indexes = self::convert_to_array($index);
        // If indexes is empty, returns false.
        if ($indexes === []) {
            return false;
        }
        $current_array = $array;
        // Grab the current index
        while ($current_index = array_shift($indexes)) {
            if ($current_index === '*') {
                $current_index = array_shift($indexes);
                foreach ($current_array as $item) {
                    if (!array_key_exists($current_index, $item)) {
                        return false;
                    }
                }
                // If indexes is empty, all elements are checked.
                if ($indexes === []) {
                    return true;
                }
                $current_array = self::dot_search('*.' . $current_index, $current_array);
                continue;
            }
            if (!array_key_exists($current_index, $current_array)) {
                return false;
            }
            $current_array = $current_array[$current_index];
        }
        return true;
    }
    /**
     * Groups all rows by their index values. Result's depth equals number of indexes
     *
     * @used-by array_group_by()
     *
     * @param array $array        Data array (i.e. from query result)
     * @param array $indexes      Indexes to group by. Dot syntax used. Returns $array if empty
     * @param bool  $includeEmpty If true, null and '' are also added as valid keys to group
     *
     * @return array Result array where rows are grouped together by indexes values.
     */
    public static function group_by(array $array, array $indexes, bool $include_empty = false): array
    {
        if ($indexes === []) {
            return $array;
        }
        $result = [];
        foreach ($array as $row) {
            $result = self::array_attach_indexed_value($result, $row, $indexes, $include_empty);
        }
        return $result;
    }
    /**
     * Recursively attach $row to the $indexes path of values found by
     * `dot_array_search()`.
     *
     * @used-by groupBy()
     */
    private static function array_attach_indexed_value(array $result, array $row, array $indexes, bool $include_empty): array
    {
        if (($index = array_shift($indexes)) === null) {
            $result[] = $row;
            return $result;
        }
        $value = dot_array_search($index, $row);
        if (!is_scalar($value)) {
            $value = '';
        }
        if (is_bool($value)) {
            $value = (int) $value;
        }
        if (!$include_empty && $value === '') {
            return $result;
        }
        if (!array_key_exists($value, $result)) {
            $result[$value] = [];
        }
        $result[$value] = self::array_attach_indexed_value($result[$value], $row, $indexes, $include_empty);
        return $result;
    }
    /**
     * Compare recursively two associative arrays and return difference as new array.
     * Returns keys that exist in `$original` but not in `$compareWith`.
     */
    public static function recursive_diff(array $original, array $compare_with): array
    {
        $difference = [];
        if ($original === []) {
            return [];
        }
        if ($compare_with === []) {
            return $original;
        }
        foreach ($original as $original_key => $original_value) {
            if ($original_value === []) {
                continue;
            }
            if (is_array($original_value)) {
                $diff_arrays = [];
                if (isset($compare_with[$original_key]) && is_array($compare_with[$original_key])) {
                    $diff_arrays = self::recursive_diff($original_value, $compare_with[$original_key]);
                } else {
                    $difference[$original_key] = $original_value;
                }
                if ($diff_arrays !== []) {
                    $difference[$original_key] = $diff_arrays;
                }
            } elseif (is_string($original_value) && !array_key_exists($original_key, $compare_with)) {
                $difference[$original_key] = $original_value;
            }
        }
        return $difference;
    }
    /**
     * Recursively count all keys.
     */
    public static function recursive_count(array $array, int $counter = 0): int
    {
        foreach ($array as $value) {
            if (is_array($value)) {
                $counter = self::recursive_count($value, $counter);
            }
            $counter++;
        }
        return $counter;
    }
    /**
     * Sorts array values in natural order
     * If the value is an array, you need to specify the $sortByIndex of the key to sort
     *
     * @param list<int|list<int|string>|string> $array
     * @param int|string|null                   $sortByIndex
     */
    public static function sort_values_by_natural(array &$array, $sort_by_index = null): bool
    {
        return usort($array, static function ($current_value, $next_value) use ($sort_by_index): int {
            if ($sort_by_index !== null) {
                return strnatcmp((string) $current_value[$sort_by_index], (string) $next_value[$sort_by_index]);
            }
            return strnatcmp((string) $current_value, (string) $next_value);
        });
    }
}