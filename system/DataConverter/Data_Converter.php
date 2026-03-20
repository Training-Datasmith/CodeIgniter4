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
namespace Code_Igniter\Data_Converter;

use Closure;
use Code_Igniter\Data_Caster\Data_Caster;
use Code_Igniter\Entity\Entity;
/**
 * PHP data <==> DataSource data converter
 *
 * @template TEntity of object
 *
 * @see \CodeIgniter\DataConverter\DataConverterTest
 */
final readonly class Data_Converter
{
    /**
     * The data caster.
     */
    private Data_Caster $data_caster;
    /**
     * @param array<string, class-string> $castHandlers Custom convert handlers
     *
     * @internal
     */
    public function __construct(
        /**
         * Type definitions.
         *
         * @var array<string, string> [column => type]
         */
        private array $types,
        array $cast_handlers = [],
        /**
         * Helper object.
         */
        private ?object $helper = null,
        /**
         * Static reconstruct method name or closure to reconstruct an object.
         * Used by reconstruct().
         *
         * @var (Closure(array<string, mixed>): TEntity)|string|null
         */
        private Closure|string|null $reconstructor = 'reconstruct',
        /**
         * Extract method name or closure to extract data from an object.
         * Used by extract().
         *
         * @var (Closure(TEntity, bool, bool): array<string, mixed>)|string|null
         */
        private Closure|string|null $extractor = null
    )
    {
        $this->data_caster = new Data_Caster($cast_handlers, $types, $this->helper);
    }
    /**
     * Converts data from DataSource to PHP array with specified type values.
     *
     * @param array<string, mixed> $data DataSource data
     *
     * @internal
     */
    public function from_data_source(array $data): array
    {
        foreach (array_keys($this->types) as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->data_caster->cast_as($data[$field], $field, 'get');
            }
        }
        return $data;
    }
    /**
     * Converts PHP array to data for DataSource field types.
     *
     * @param array<string, mixed> $phpData PHP data
     *
     * @internal
     */
    public function to_data_source(array $php_data): array
    {
        foreach (array_keys($this->types) as $field) {
            if (array_key_exists($field, $php_data)) {
                $php_data[$field] = $this->data_caster->cast_as($php_data[$field], $field, 'set');
            }
        }
        return $php_data;
    }
    /**
     * Takes database data array and creates a specified type object.
     *
     * @param class-string<TEntity> $classname
     * @param array<string, mixed>  $row       Raw data from database
     *
     * @return TEntity
     *
     * @internal
     */
    public function reconstruct(string $classname, array $row): object
    {
        $php_data = $this->from_data_source($row);
        // Use static reconstruct method.
        if (is_string($this->reconstructor) && method_exists($classname, $this->reconstructor)) {
            $method = $this->reconstructor;
            return $classname::$method($php_data);
        }
        // Use closure to reconstruct.
        if ($this->reconstructor instanceof Closure) {
            $closure = $this->reconstructor;
            return $closure($php_data);
        }
        $class_obj = new $classname();
        if ($class_obj instanceof Entity) {
            $class_obj->inject_raw_data($php_data);
            $class_obj->sync_original();
            return $class_obj;
        }
        $class_set = Closure::bind(function ($key, $value): void {
            $this->{$key} = $value;
        }, $class_obj, $classname);
        foreach ($php_data as $key => $value) {
            $class_set($key, $value);
        }
        return $class_obj;
    }
    /**
     * Takes an object and extract properties as an array.
     *
     * @param bool $onlyChanged Only for CodeIgniter's Entity. If true, only returns
     *                          values that have changed since object creation.
     * @param bool $recursive   Only for CodeIgniter's Entity. If true, inner
     *                          entities will be cast as array as well.
     *
     * @return array<string, mixed>
     *
     * @internal
     */
    public function extract(object $object, bool $only_changed = false, bool $recursive = false): array
    {
        // Use extractor method.
        if (is_string($this->extractor) && method_exists($object, $this->extractor)) {
            $method = $this->extractor;
            $row = $object->{$method}($only_changed, $recursive);
            return $this->to_data_source($row);
        }
        // Use closure to extract.
        if ($this->extractor instanceof Closure) {
            $closure = $this->extractor;
            $row = $closure($object, $only_changed, $recursive);
            return $this->to_data_source($row);
        }
        if ($object instanceof Entity) {
            $row = $object->to_raw_array($only_changed, $recursive);
            return $this->to_data_source($row);
        }
        $array = (array) $object;
        $row = [];
        foreach ($array as $key => $value) {
            $key = preg_replace('/\000.*\000/', '', $key);
            $row[$key] = $value;
        }
        return $this->to_data_source($row);
    }
}