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
namespace Code_Igniter\Entity;

use Backed_Enum;
use Code_Igniter\Data_Caster\Data_Caster;
use Code_Igniter\Entity\Cast\Array_Cast;
use Code_Igniter\Entity\Cast\Boolean_Cast;
use Code_Igniter\Entity\Cast\Csv_Cast;
use Code_Igniter\Entity\Cast\Datetime_Cast;
use Code_Igniter\Entity\Cast\Enum_Cast;
use Code_Igniter\Entity\Cast\Float_Cast;
use Code_Igniter\Entity\Cast\Int_Bool_Cast;
use Code_Igniter\Entity\Cast\Integer_Cast;
use Code_Igniter\Entity\Cast\Json_Cast;
use Code_Igniter\Entity\Cast\Object_Cast;
use Code_Igniter\Entity\Cast\String_Cast;
use Code_Igniter\Entity\Cast\Timestamp_Cast;
use Code_Igniter\Entity\Cast\Uri_Cast;
use Code_Igniter\Entity\Exceptions\Cast_Exception;
use Code_Igniter\I18n\Time;
use DateTimeInterface;
use Exception;
use JsonSerializable;
use Traversable;
use Unit_Enum;
/**
 * Entity encapsulation, for use with CodeIgniter\Model
 *
 * @see \CodeIgniter\Entity\EntityTest
 */
class Entity implements JsonSerializable
{
    /**
     * Maps names used in sets and gets against unique
     * names within the class, allowing independence from
     * database column names.
     *
     * Example:
     *  $datamap = [
     *      'class_property_name' => 'db_column_name'
     *  ];
     *
     * @var array<string, string>
     */
    protected $datamap = [];
    /**
     * The date fields.
     *
     * @var list<string>
     */
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    /**
     * Array of field names and the type of value to cast them as when
     * they are accessed.
     *
     * @var array<string, string>
     */
    protected $casts = [];
    /**
     * Custom convert handlers.
     *
     * @var array<string, string>
     */
    protected $cast_handlers = [];
    /**
     * Default convert handlers.
     *
     * @var array<string, string>
     */
    private array $default_cast_handlers = ['array' => Array_Cast::class, 'bool' => Boolean_Cast::class, 'boolean' => Boolean_Cast::class, 'csv' => Csv_Cast::class, 'datetime' => Datetime_Cast::class, 'double' => Float_Cast::class, 'enum' => Enum_Cast::class, 'float' => Float_Cast::class, 'int' => Integer_Cast::class, 'integer' => Integer_Cast::class, 'int-bool' => Int_Bool_Cast::class, 'json' => Json_Cast::class, 'object' => Object_Cast::class, 'string' => String_Cast::class, 'timestamp' => Timestamp_Cast::class, 'uri' => Uri_Cast::class];
    /**
     * Holds the current values of all class vars.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [];
    /**
     * Holds original copies of all class vars so we can determine
     * what's actually been changed and not accidentally write
     * nulls where we shouldn't.
     *
     * @var array<string, mixed>
     */
    protected $original = [];
    /**
     * The data caster.
     */
    protected ?Data_Caster $data_caster = null;
    /**
     * Holds info whenever properties have to be casted.
     */
    private bool $_cast = true;
    /**
     * Indicates whether all attributes are scalars (for optimization).
     */
    private bool $_only_scalars = true;
    /**
     * Allows filling in Entity parameters during construction.
     *
     * @param array<string, mixed> $data
     */
    public function __construct(?array $data = null)
    {
        $this->data_caster = $this->data_caster();
        $this->sync_original();
        $this->fill($data);
    }
    /**
     * Takes an array of key/value pairs and sets them as class
     * properties, using any `setCamelCasedProperty()` methods
     * that may or may not exist.
     *
     * @param array<string, array<int|string, mixed>|bool|float|int|object|string|null> $data
     *
     * @return $this
     */
    public function fill(?array $data = null)
    {
        if (!is_array($data)) {
            return $this;
        }
        foreach ($data as $key => $value) {
            $this->__set($key, $value);
        }
        return $this;
    }
    /**
     * General method that will return all public and protected values
     * of this entity as an array. All values are accessed through the
     * __get() magic method so will have any casts, etc applied to them.
     *
     * @param bool $onlyChanged If true, only return values that have changed since object creation.
     * @param bool $cast        If true, properties will be cast.
     * @param bool $recursive   If true, inner entities will be cast as array as well.
     *
     * @return array<string, mixed>
     */
    public function to_array(bool $only_changed = false, bool $cast = true, bool $recursive = false): array
    {
        $original_cast = $this->_cast;
        $this->_cast = $cast;
        $keys = array_filter(array_keys($this->attributes), static fn($key): bool => !str_starts_with($key, '_'));
        if (is_array($this->datamap)) {
            $keys = array_unique([...array_diff($keys, $this->datamap), ...array_keys($this->datamap)]);
        }
        $return = [];
        // Loop over the properties, to allow magic methods to do their thing.
        foreach ($keys as $key) {
            if ($only_changed && !$this->has_changed($key)) {
                continue;
            }
            $return[$key] = $this->__get($key);
            if ($recursive) {
                if ($return[$key] instanceof self) {
                    $return[$key] = $return[$key]->to_array($only_changed, $cast, $recursive);
                } elseif (is_callable([$return[$key], 'toArray'])) {
                    $return[$key] = $return[$key]->to_array();
                }
            }
        }
        $this->_cast = $original_cast;
        return $return;
    }
    /**
     * Returns the raw values of the current attributes.
     *
     * @param bool $onlyChanged If true, only return values that have changed since object creation.
     * @param bool $recursive   If true, inner entities will be cast as array as well.
     *
     * @return array<string, mixed>
     */
    public function to_raw_array(bool $only_changed = false, bool $recursive = false): array
    {
        $convert = static function ($value) use (&$convert, $recursive) {
            if (!$recursive) {
                return $value;
            }
            if ($value instanceof self) {
                // Always output full array for nested entities
                return $value->to_raw_array(false, true);
            }
            if (is_array($value)) {
                $result = [];
                foreach ($value as $k => $v) {
                    $result[$k] = $convert($v);
                }
                return $result;
            }
            if (is_object($value) && is_callable([$value, 'toRawArray'])) {
                return $value->to_raw_array();
            }
            return $value;
        };
        // When returning everything
        if (!$only_changed) {
            return $recursive ? array_map($convert, $this->attributes) : $this->attributes;
        }
        // When filtering by changed values only
        $return = [];
        foreach ($this->attributes as $key => $value) {
            // Special handling for arrays of entities in recursive mode
            // Skip hasChanged() and do per-entity comparison directly
            if ($recursive && is_array($value) && $this->contains_only_entities($value)) {
                $original_value = $this->original[$key] ?? null;
                if (!is_string($original_value)) {
                    // No original or invalid format, export all entities
                    $converted = [];
                    foreach ($value as $idx => $item) {
                        $converted[$idx] = $item->to_raw_array(false, true);
                    }
                    $return[$key] = $converted;
                    continue;
                }
                // Decode original array structure for per-entity comparison
                $original_array = json_decode($original_value, true);
                $converted = [];
                foreach ($value as $idx => $item) {
                    // Compare current entity against its original state
                    $current_normalized = $this->normalize_value($item);
                    $original_normalized = $original_array[$idx] ?? null;
                    // Only include if changed, new, or can't determine
                    if ($original_normalized === null || $current_normalized !== $original_normalized) {
                        $converted[$idx] = $item->to_raw_array(false, true);
                    }
                }
                // Only include this property if at least one entity changed
                if ($converted !== []) {
                    $return[$key] = $converted;
                }
                continue;
            }
            // For all other cases, use hasChanged()
            if (!$this->has_changed($key)) {
                continue;
            }
            if ($recursive) {
                // Special handling for arrays (mixed or not all entities)
                if (is_array($value)) {
                    $converted = [];
                    foreach ($value as $idx => $item) {
                        $converted[$idx] = $item instanceof self ? $item->to_raw_array(false, true) : $convert($item);
                    }
                    $return[$key] = $converted;
                    continue;
                }
                // default recursive conversion
                $return[$key] = $convert($value);
                continue;
            }
            // non-recursive changed value
            $return[$key] = $value;
        }
        return $return;
    }
    /**
     * Ensures our "original" values match the current values.
     *
     * Objects and arrays are normalized and JSON-encoded for reliable change detection,
     * while scalars are stored as-is for performance.
     *
     * @return $this
     */
    public function sync_original()
    {
        $this->original = [];
        $this->_only_scalars = true;
        foreach ($this->attributes as $key => $value) {
            if (is_object($value) || is_array($value)) {
                $this->original[$key] = json_encode($this->normalize_value($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $this->_only_scalars = false;
            } else {
                $this->original[$key] = $value;
            }
        }
        return $this;
    }
    /**
     * Checks a property to see if it has changed since the entity
     * was created. Or, without a parameter, checks if any
     * properties have changed.
     */
    public function has_changed(?string $key = null): bool
    {
        // If no parameter was given then check all attributes
        if ($key === null) {
            if ($this->_only_scalars) {
                return $this->original !== $this->attributes;
            }
            foreach (array_keys($this->attributes) as $attribute_key) {
                if ($this->has_changed($attribute_key)) {
                    return true;
                }
            }
            return false;
        }
        $db_column = $this->map_property($key);
        // Key doesn't exist in either
        if (!array_key_exists($db_column, $this->original) && !array_key_exists($db_column, $this->attributes)) {
            return false;
        }
        // It's a new element
        if (!array_key_exists($db_column, $this->original) && array_key_exists($db_column, $this->attributes)) {
            return true;
        }
        // It was removed
        if (array_key_exists($db_column, $this->original) && !array_key_exists($db_column, $this->attributes)) {
            return true;
        }
        $original_value = $this->original[$db_column];
        $current_value = $this->attributes[$db_column];
        // If original is a string, it was JSON-encoded (object or array)
        if (is_string($original_value) && (is_object($current_value) || is_array($current_value))) {
            return $original_value !== json_encode($this->normalize_value($current_value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        // For scalars, use direct comparison
        return $original_value !== $current_value;
    }
    /**
     * Checks if an array contains only Entity instances.
     * This allows optimization for per-entity change tracking.
     *
     * @param array<int|string, mixed> $data
     */
    private function contains_only_entities(array $data): bool
    {
        if ($data === []) {
            return false;
        }
        foreach ($data as $item) {
            if (!$item instanceof self) {
                return false;
            }
        }
        return true;
    }
    /**
     * Recursively normalize a value for comparison.
     * Converts objects and arrays to a JSON-encodable format.
     */
    private function normalize_value(mixed $data): mixed
    {
        if (is_array($data)) {
            $normalized = [];
            foreach ($data as $key => $value) {
                $normalized[$key] = $this->normalize_value($value);
            }
            return $normalized;
        }
        if (is_object($data)) {
            // Check for Entity instance (use raw values, recursive)
            if ($data instanceof self) {
                $object_data = $data->to_raw_array(false, true);
            } elseif ($data instanceof JsonSerializable) {
                $object_data = $data->jsonSerialize();
            } elseif (method_exists($data, 'toArray')) {
                $object_data = $data->to_array();
            } elseif ($data instanceof Traversable) {
                $object_data = iterator_to_array($data);
            } elseif ($data instanceof DateTimeInterface) {
                return ['__class' => $data::class, '__datetime' => $data->format(DATE_RFC3339_EXTENDED)];
            } elseif ($data instanceof Unit_Enum) {
                return ['__class' => $data::class, '__enum' => $data instanceof Backed_Enum ? $data->value : $data->name];
            } else {
                $object_data = get_object_vars($data);
                // Fallback for value objects with __toString()
                // when properties are not accessible
                if ($object_data === [] && method_exists($data, '__toString')) {
                    return ['__class' => $data::class, '__string' => (string) $data];
                }
            }
            return ['__class' => $data::class, '__data' => $this->normalize_value($object_data)];
        }
        // Return scalars and null as-is
        return $data;
    }
    /**
     * Set raw data array without any mutations.
     *
     * @param array<string, mixed> $data
     *
     * @return $this
     */
    public function inject_raw_data(array $data)
    {
        $this->attributes = $data;
        $this->sync_original();
        return $this;
    }
    /**
     * Checks the datamap to see if this property name is being mapped,
     * and returns the DB column name, if any, or the original property name.
     *
     * @return string Database column name.
     */
    protected function map_property(string $key)
    {
        if ($this->datamap === []) {
            return $key;
        }
        if (array_key_exists($key, $this->datamap) && $this->datamap[$key] !== '') {
            return $this->datamap[$key];
        }
        return $key;
    }
    /**
     * Converts the given string|timestamp|DateTimeInterface instance
     * into the "CodeIgniter\I18n\Time" object.
     *
     * @param DateTimeInterface|float|int|string $value
     *
     * @return Time
     *
     * @throws Exception
     */
    protected function mutate_date($value)
    {
        return Datetime_Cast::get($value);
    }
    /**
     * Provides the ability to cast an item as a specific data type.
     * Add ? at the beginning of the type (i.e. ?string) to get `null`
     * instead of casting $value when $value is null.
     *
     * @param bool|float|int|string|null $value     Attribute value
     * @param string                     $attribute Attribute name
     * @param string                     $method    Allowed to "get" and "set"
     *
     * @return array<int|string, mixed>|bool|float|int|object|string|null
     *
     * @throws CastException
     */
    protected function cast_as($value, string $attribute, string $method = 'get')
    {
        if ($this->data_caster() instanceof Data_Caster) {
            return $this->data_caster->set_types($this->casts)->cast_as($value, $attribute, $method);
        }
        return $value;
    }
    /**
     * Returns a DataCaster instance when casts are defined.
     * If no casts are configured, no DataCaster is created and null is returned.
     */
    protected function data_caster(): ?Data_Caster
    {
        if ($this->casts === []) {
            $this->data_caster = null;
            return null;
        }
        if (!$this->data_caster instanceof Data_Caster) {
            $this->data_caster = new Data_Caster(array_merge($this->default_cast_handlers, $this->cast_handlers), null, null, false);
        }
        return $this->data_caster;
    }
    /**
     * Support for json_encode().
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->to_array();
    }
    /**
     * Change the value of the private $_cast property.
     *
     * @return bool|Entity
     */
    public function cast(?bool $cast = null)
    {
        if ($cast === null) {
            return $this->_cast;
        }
        $this->_cast = $cast;
        return $this;
    }
    /**
     * Magic method to all protected/private class properties to be
     * easily set, either through a direct access or a
     * `setCamelCasedProperty()` method.
     *
     * Examples:
     *  $this->my_property = $p;
     *  $this->setMyProperty() = $p;
     *
     * @param array<int|string, mixed>|bool|float|int|object|string|null $value
     *
     * @return void
     *
     * @throws Exception
     */
    public function __set(string $key, $value = null)
    {
        $db_column = $this->map_property($key);
        // Check if the field should be mutated into a date
        if (in_array($db_column, $this->dates, true)) {
            $value = $this->mutate_date($value);
        }
        $value = $this->cast_as($value, $db_column, 'set');
        // if a setter method exists for this key, use that method to
        // insert this value. should be outside $isNullable check,
        // so maybe wants to do sth with null value automatically
        $method = 'set' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $db_column)));
        // If a "`_set` + $key" method exists, it is a setter.
        if (method_exists($this, '_' . $method)) {
            $this->{'_' . $method}($value);
            return;
        }
        // If a "`set` + $key" method exists, it is also a setter.
        if (method_exists($this, $method)) {
            $this->{$method}($value);
            return;
        }
        // Otherwise, just the value. This allows for creation of new
        // class properties that are undefined, though they cannot be
        // saved. Useful for grabbing values through joins, assigning
        // relationships, etc.
        $this->attributes[$db_column] = $value;
    }
    /**
     * Magic method to allow retrieval of protected and private class properties
     * either by their name, or through a `getCamelCasedProperty()` method.
     *
     * Examples:
     *  $p = $this->my_property
     *  $p = $this->getMyProperty()
     *
     * @return array<int|string, mixed>|bool|float|int|object|string|null
     *
     * @throws Exception
     */
    public function __get(string $key)
    {
        $db_column = $this->map_property($key);
        $result = null;
        // Convert to CamelCase for the method
        $method = 'get' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $db_column)));
        // if a getter method exists for this key,
        // use that method to insert this value.
        if (method_exists($this, '_' . $method)) {
            // If a "`_get` + $key" method exists, it is a getter.
            $result = $this->{'_' . $method}();
        } elseif (method_exists($this, $method)) {
            // If a "`get` + $key" method exists, it is also a getter.
            $result = $this->{$method}();
        } elseif (array_key_exists($db_column, $this->attributes)) {
            $result = $this->attributes[$db_column];
        }
        // Do we need to mutate this into a date?
        if (in_array($db_column, $this->dates, true)) {
            $result = $this->mutate_date($result);
        } elseif ($this->_cast) {
            $result = $this->cast_as($result, $db_column);
        }
        return $result;
    }
    /**
     * Returns true if a property exists names $key, or a getter method
     * exists named like for __get().
     */
    public function __isset(string $key): bool
    {
        if ($this->is_mapped_db_column($key)) {
            return false;
        }
        $db_column = $this->map_property($key);
        $method = 'get' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $db_column)));
        if (method_exists($this, $method)) {
            return true;
        }
        return isset($this->attributes[$db_column]);
    }
    /**
     * Unsets an attribute property.
     */
    public function __unset(string $key): void
    {
        if ($this->is_mapped_db_column($key)) {
            return;
        }
        $db_column = $this->map_property($key);
        unset($this->attributes[$db_column]);
    }
    /**
     * Whether this key is mapped db column name?
     */
    protected function is_mapped_db_column(string $key): bool
    {
        $db_column = $this->map_property($key);
        // The $key is a property name which has mapped db column name
        if ($key !== $db_column) {
            return false;
        }
        return $this->has_mapped_property($key);
    }
    /**
     * Whether this key has mapped property?
     */
    protected function has_mapped_property(string $key): bool
    {
        $property = array_search($key, $this->datamap, true);
        return $property !== false;
    }
}