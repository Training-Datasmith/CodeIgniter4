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
namespace Code_Igniter\Data_Caster;

use Code_Igniter\Data_Caster\Cast\Array_Cast;
use Code_Igniter\Data_Caster\Cast\Boolean_Cast;
use Code_Igniter\Data_Caster\Cast\Cast_Interface;
use Code_Igniter\Data_Caster\Cast\Csv_Cast;
use Code_Igniter\Data_Caster\Cast\Datetime_Cast;
use Code_Igniter\Data_Caster\Cast\Enum_Cast;
use Code_Igniter\Data_Caster\Cast\Float_Cast;
use Code_Igniter\Data_Caster\Cast\Int_Bool_Cast;
use Code_Igniter\Data_Caster\Cast\Integer_Cast;
use Code_Igniter\Data_Caster\Cast\Json_Cast;
use Code_Igniter\Data_Caster\Cast\Timestamp_Cast;
use Code_Igniter\Data_Caster\Cast\Uri_Cast;
use Code_Igniter\Entity\Cast\Cast_Interface as EntityCastInterface;
use Code_Igniter\Entity\Exceptions\Cast_Exception;
use Code_Igniter\Exceptions\InvalidArgumentException;
/**
 * @phpstan-type cast_handlers array<string, class-string<EntityCastInterface|CastInterface>>
 *
 * @see CodeIgniter\DataCaster\DataCasterTest
 * @see CodeIgniter\Entity\EntityTest
 */
final class Data_Caster
{
    /**
     * Array of field names and the type of value to cast.
     *
     * @var array<string, string> [field => type]
     */
    private array $types = [];
    /**
     * Convert handlers.
     *
     * @var cast_handlers [type => classname]
     */
    private array $cast_handlers = ['array' => Array_Cast::class, 'bool' => Boolean_Cast::class, 'boolean' => Boolean_Cast::class, 'csv' => Csv_Cast::class, 'datetime' => Datetime_Cast::class, 'enum' => Enum_Cast::class, 'double' => Float_Cast::class, 'float' => Float_Cast::class, 'int' => Integer_Cast::class, 'integer' => Integer_Cast::class, 'int-bool' => Int_Bool_Cast::class, 'json' => Json_Cast::class, 'timestamp' => Timestamp_Cast::class, 'uri' => Uri_Cast::class];
    /**
     * @param cast_handlers|null         $castHandlers Custom convert handlers
     * @param array<string, string>|null $types        [field => type]
     * @param object|null                $helper       Helper object.
     * @param bool                       $strict       Strict mode? Set to `false` for casts for Entity.
     */
    public function __construct(?array $cast_handlers = null, ?array $types = null, private readonly ?object $helper = null, private readonly bool $strict = true)
    {
        $this->cast_handlers = array_merge($this->cast_handlers, $cast_handlers ?? []);
        if ($types !== null) {
            $this->set_types($types);
        }
        if ($this->strict) {
            foreach ($this->cast_handlers as $handler) {
                if (!is_subclass_of($handler, Cast_Interface::class) && !is_subclass_of($handler, Entity_Cast_Interface::class)) {
                    throw new InvalidArgumentException('Invalid class type. It must implement CastInterface. class: ' . $handler);
                }
            }
        }
    }
    /**
     * This method is only for Entity.
     *
     * @TODO if Entity::$casts is readonly, we don't need this method.
     *
     * @param array<string, string> $types [field => type]
     *
     * @return $this
     *
     * @internal
     */
    public function set_types(array $types): static
    {
        $this->types = $types;
        return $this;
    }
    /**
     * Provides the ability to cast an item as a specific data type.
     * Add ? at the beginning of the type (i.e. ?string) to get `null`
     * instead of casting $value when $value is null.
     *
     * @param mixed  $value  The value to convert
     * @param string $field  The field name
     * @param string $method Allowed to "get" and "set"
     */
    public function cast_as(mixed $value, string $field, string $method = 'get'): mixed
    {
        if ($method !== 'get' && $method !== 'set') {
            throw Cast_Exception::for_invalid_method($method);
        }
        // If the type is not defined, return as it is.
        if (!isset($this->types[$field])) {
            return $value;
        }
        $type = $this->types[$field];
        $is_nullable = false;
        // Is nullable?
        if (str_starts_with($type, '?')) {
            $is_nullable = true;
            if ($value === null) {
                return null;
            }
            $type = substr($type, 1);
        } elseif ($value === null) {
            if ($this->strict) {
                $message = 'Field "' . $field . '" is not nullable, but null was passed.';
                throw new InvalidArgumentException($message);
            }
        }
        // In order not to create a separate handler for the
        // json-array type, we transform the required one.
        $type = $type === 'json-array' ? 'json[array]' : $type;
        $params = [];
        // Attempt to retrieve additional parameters if specified
        // type[param, param2,param3]
        if (preg_match('/\A(.+)\[(.+)\]\z/', $type, $matches)) {
            $type = $matches[1];
            $params = array_map(trim(...), explode(',', $matches[2]));
        }
        if ($is_nullable && !$this->strict) {
            $params[] = 'nullable';
        }
        $type = trim($type, '[]');
        $handlers = $this->cast_handlers;
        if (!isset($handlers[$type])) {
            throw new InvalidArgumentException('No such handler for "' . $field . '". Invalid type: ' . $type);
        }
        $handler = $handlers[$type];
        if (!$this->strict && !is_subclass_of($handler, Cast_Interface::class) && !is_subclass_of($handler, Entity_Cast_Interface::class)) {
            throw Cast_Exception::for_invalid_interface($handler);
        }
        return $handler::$method($value, $params, $this->helper);
    }
}