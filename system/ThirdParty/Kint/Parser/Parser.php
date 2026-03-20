<?php

declare (strict_types=1);
/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2013 Jonathan Vollebregt (jnvsor@gmail.com), Rokas Šleinius (raveren@gmail.com)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 * IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
namespace Kint\Parser;

use DomainException;
use InvalidArgumentException;
use Kint\Utils;
use Kint\Value\Abstract_Value;
use Kint\Value\Array_Value;
use Kint\Value\Closed_Resource_Value;
use Kint\Value\Context\Array_Context;
use Kint\Value\Context\Class_Declared_Context;
use Kint\Value\Context\Class_Owned_Context;
use Kint\Value\Context\Context_Interface;
use Kint\Value\Context\Property_Context;
use Kint\Value\Fixed_Width_Value;
use Kint\Value\Instance_Value;
use Kint\Value\Representation\Container_Representation;
use Kint\Value\Representation\String_Representation;
use Kint\Value\Resource_Value;
use Kint\Value\String_Value;
use Kint\Value\Uninitialized_Value;
use Kint\Value\Unknown_Value;
use Kint\Value\Virtual_Value;
use ReflectionClass;
use Reflection_Object;
use ReflectionProperty;
use Reflection_Reference;
use Throwable;
/**
 * @psalm-type ParserTrigger int-mask-of<Parser::TRIGGER_*>
 */
class Parser
{
    /**
     * Plugin triggers.
     *
     * These are constants indicating trigger points for plugins
     *
     * BEGIN: Before normal parsing
     * SUCCESS: After successful parsing
     * RECURSION: After parsing cancelled by recursion
     * DEPTH_LIMIT: After parsing cancelled by depth limit
     * COMPLETE: SUCCESS | RECURSION | DEPTH_LIMIT
     *
     * While a plugin's getTriggers may return any of these only one should
     * be given to the plugin when PluginInterface::parse is called
     */
    public const TRIGGER_NONE = 0;
    public const TRIGGER_BEGIN = 1 << 0;
    public const TRIGGER_SUCCESS = 1 << 1;
    public const TRIGGER_RECURSION = 1 << 2;
    public const TRIGGER_DEPTH_LIMIT = 1 << 3;
    public const TRIGGER_COMPLETE = self::TRIGGER_SUCCESS | self::TRIGGER_RECURSION | self::TRIGGER_DEPTH_LIMIT;
    /** @psalm-var ?class-string */
    protected ?string $caller_class;
    protected int $depth_limit = 0;
    protected array $array_ref_stack = [];
    protected array $object_hashes = [];
    protected array $plugins = [];
    /**
     * @param int     $depth_limit Maximum depth to parse data
     * @param ?string $caller      Caller class name
     *
     * @psalm-param ?class-string $caller
     */
    public function __construct(int $depth_limit = 0, ?string $caller = null)
    {
        $this->depth_limit = $depth_limit;
        $this->caller_class = $caller;
    }
    /**
     * Set the caller class.
     *
     * @psalm-param ?class-string $caller
     */
    public function set_caller_class(?string $caller = null): void
    {
        $this->no_recurse_call();
        $this->caller_class = $caller;
    }
    /** @psalm-return ?class-string */
    public function get_caller_class(): ?string
    {
        return $this->caller_class;
    }
    /**
     * Set the depth limit.
     *
     * @param int $depth_limit Maximum depth to parse data, 0 for none
     */
    public function set_depth_limit(int $depth_limit = 0): void
    {
        $this->no_recurse_call();
        $this->depth_limit = $depth_limit;
    }
    public function get_depth_limit(): int
    {
        return $this->depth_limit;
    }
    /**
     * Parses a variable into a Kint object structure.
     *
     * @param mixed &$var The input variable
     */
    public function parse(&$var, Context_Interface $c): Abstract_Value
    {
        $type = \strtolower(\gettype($var));
        if ($v = $this->apply_plugins_begin($var, $c, $type)) {
            return $v;
        }
        switch ($type) {
            case 'array':
                return $this->parse_array($var, $c);
            case 'boolean':
            case 'double':
            case 'integer':
            case 'null':
                return $this->parse_fixed_width($var, $c);
            case 'object':
                return $this->parse_object($var, $c);
            case 'resource':
                return $this->parse_resource($var, $c);
            case 'string':
                return $this->parse_string($var, $c);
            case 'resource (closed)':
                return $this->parse_resource_closed($var, $c);
            case 'unknown type':
            // @codeCoverageIgnore
            default:
                // These should never happen. Unknown is resource (closed) from old
                // PHP versions and there shouldn't be any other types.
                return $this->parse_unknown($var, $c);
        }
    }
    public function add_plugin(Plugin_Interface $p): void
    {
        try {
            $this->no_recurse_call();
        } catch (DomainException $e) {
            // @codeCoverageIgnore
            \trigger_error('Calling Kint\Parser::addPlugin from inside a parse is deprecated', E_USER_DEPRECATED);
            // @codeCoverageIgnore
        }
        if (!$types = $p->get_types()) {
            return;
        }
        if (!$triggers = $p->get_triggers()) {
            return;
        }
        if ($triggers & self::TRIGGER_BEGIN && !$p instanceof Plugin_Begin_Interface) {
            throw new InvalidArgumentException('Parsers triggered on begin must implement PluginBeginInterface');
        }
        if ($triggers & self::TRIGGER_COMPLETE && !$p instanceof Plugin_Complete_Interface) {
            throw new InvalidArgumentException('Parsers triggered on completion must implement PluginCompleteInterface');
        }
        $p->set_parser($this);
        foreach ($types as $type) {
            $this->plugins[$type] ??= [self::TRIGGER_BEGIN => [], self::TRIGGER_SUCCESS => [], self::TRIGGER_RECURSION => [], self::TRIGGER_DEPTH_LIMIT => []];
            foreach ($this->plugins[$type] as $trigger => &$pool) {
                if ($triggers & $trigger) {
                    $pool[] = $p;
                }
            }
        }
    }
    public function clear_plugins(): void
    {
        try {
            $this->no_recurse_call();
        } catch (DomainException $e) {
            // @codeCoverageIgnore
            \trigger_error('Calling Kint\Parser::clearPlugins from inside a parse is deprecated', E_USER_DEPRECATED);
            // @codeCoverageIgnore
        }
        $this->plugins = [];
    }
    protected function no_recurse_call(): void
    {
        $bt = \debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);
        \reset($bt);
        /** @psalm-var array{class: class-string, function: string, ...} $caller_frame */
        $caller_frame = \next($bt);
        foreach ($bt as $frame) {
            if (isset($frame['object']) && $frame['object'] === $this && 'parse' === $frame['function']) {
                throw new DomainException($caller_frame['class'] . '::' . $caller_frame['function'] . ' cannot be called from inside a parse');
            }
        }
    }
    /**
     * @psalm-param null|bool|float|int &$var
     */
    private function parse_fixed_width(&$var, Context_Interface $c): Abstract_Value
    {
        $v = new Fixed_Width_Value($c, $var);
        return $this->apply_plugins_complete($var, $v, self::TRIGGER_SUCCESS);
    }
    private function parse_string(string &$var, Context_Interface $c): Abstract_Value
    {
        $string = new String_Value($c, $var, Utils::detect_encoding($var));
        if (false !== $string->get_encoding() && \strlen($var)) {
            $string->add_representation(new String_Representation('Contents', $var, null, true));
        }
        return $this->apply_plugins_complete($var, $string, self::TRIGGER_SUCCESS);
    }
    private function parse_array(array &$var, Context_Interface $c): Abstract_Value
    {
        $size = \count($var);
        $contents = [];
        $parent_ref = Reflection_Reference::from_array_element([&$var], 0)->get_id();
        if (isset($this->array_ref_stack[$parent_ref])) {
            $array = new Array_Value($c, $size, $contents);
            $array->flags |= Abstract_Value::FLAG_RECURSION;
            return $this->apply_plugins_complete($var, $array, self::TRIGGER_RECURSION);
        }
        try {
            $this->array_ref_stack[$parent_ref] = true;
            $cdepth = $c->get_depth();
            $ap = $c->get_access_path();
            if ($size > 0 && $this->depth_limit && $cdepth >= $this->depth_limit) {
                $array = new Array_Value($c, $size, $contents);
                $array->flags |= Abstract_Value::FLAG_DEPTH_LIMIT;
                return $this->apply_plugins_complete($var, $array, self::TRIGGER_DEPTH_LIMIT);
            }
            foreach ($var as $key => $_) {
                $child = new Array_Context($key);
                $child->depth = $cdepth + 1;
                $child->reference = null !== Reflection_Reference::from_array_element($var, $key);
                if (null !== $ap) {
                    $child->access_path = $ap . '[' . \var_export($key, true) . ']';
                }
                $contents[$key] = $this->parse($var[$key], $child);
            }
            $array = new Array_Value($c, $size, $contents);
            if ($contents) {
                $array->add_representation(new Container_Representation('Contents', $contents, null, true));
            }
            return $this->apply_plugins_complete($var, $array, self::TRIGGER_SUCCESS);
        } finally {
            unset($this->array_ref_stack[$parent_ref]);
        }
    }
    /**
     * @psalm-return ReflectionProperty[]
     */
    private function get_props_ordered(ReflectionClass $r): array
    {
        if ($parent = $r->get_parent_class()) {
            $props = self::get_props_ordered($parent);
        } else {
            $props = [];
        }
        foreach ($r->get_properties() as $prop) {
            if ($prop->is_static()) {
                continue;
            }
            if ($prop->is_private()) {
                $props[] = $prop;
            } else {
                $props[$prop->name] = $prop;
            }
        }
        return $props;
    }
    /**
     * @codeCoverageIgnore
     *
     * @psalm-return ReflectionProperty[]
     */
    private function get_props_ordered_old(ReflectionClass $r): array
    {
        $props = [];
        foreach ($r->get_properties() as $prop) {
            if ($prop->is_static()) {
                continue;
            }
            $props[] = $prop;
        }
        while ($r = $r->get_parent_class()) {
            foreach ($r->get_properties(ReflectionProperty::IS_PRIVATE) as $prop) {
                if ($prop->is_static()) {
                    continue;
                }
                $props[] = $prop;
            }
        }
        return $props;
    }
    private function parse_object(object &$var, Context_Interface $c): Abstract_Value
    {
        $hash = \spl_object_hash($var);
        $classname = \get_class($var);
        if (isset($this->object_hashes[$hash])) {
            $object = new Instance_Value($c, $classname, $hash, \spl_object_id($var));
            $object->flags |= Abstract_Value::FLAG_RECURSION;
            return $this->apply_plugins_complete($var, $object, self::TRIGGER_RECURSION);
        }
        try {
            $this->object_hashes[$hash] = true;
            $cdepth = $c->get_depth();
            $ap = $c->get_access_path();
            if ($this->depth_limit && $cdepth >= $this->depth_limit) {
                $object = new Instance_Value($c, $classname, $hash, \spl_object_id($var));
                $object->flags |= Abstract_Value::FLAG_DEPTH_LIMIT;
                return $this->apply_plugins_complete($var, $object, self::TRIGGER_DEPTH_LIMIT);
            }
            if (KINT_PHP81) {
                $props = $this->get_props_ordered(new Reflection_Object($var));
            } else {
                $props = $this->get_props_ordered_old(new Reflection_Object($var));
                // @codeCoverageIgnore
            }
            $values = (array) $var;
            $properties = [];
            foreach ($props as $rprop) {
                if (KINT_PHP81 === false) {
                    $rprop->set_accessible(true);
                }
                $name = $rprop->get_name();
                // Casting object to array:
                // private properties show in the form "\0$owner_class_name\0$property_name";
                // protected properties show in the form "\0*\0$property_name";
                // public properties show in the form "$property_name";
                // http://www.php.net/manual/en/language.types.array.php#language.types.array.casting
                $key = $name;
                if ($rprop->is_protected()) {
                    $key = "\x00*\x00" . $name;
                } elseif ($rprop->is_private()) {
                    $key = "\x00" . $rprop->get_declaring_class()->get_name() . "\x00" . $name;
                }
                $initialized = \array_key_exists($key, $values);
                if ($key === (string) (int) $key) {
                    $key = (int) $key;
                }
                if ($rprop->is_default()) {
                    $child = new Property_Context($name, $rprop->get_declaring_class()->get_name(), Class_Declared_Context::ACCESS_PUBLIC);
                    $child->readonly = KINT_PHP81 && $rprop->is_read_only();
                    if ($rprop->is_protected()) {
                        $child->access = Class_Declared_Context::ACCESS_PROTECTED;
                    } elseif ($rprop->is_private()) {
                        $child->access = Class_Declared_Context::ACCESS_PRIVATE;
                    }
                    if (KINT_PHP84) {
                        if ($rprop->is_protected_set()) {
                            $child->access_set = Class_Declared_Context::ACCESS_PROTECTED;
                        } elseif ($rprop->is_private_set()) {
                            $child->access_set = Class_Declared_Context::ACCESS_PRIVATE;
                        }
                        $hooks = $rprop->get_hooks();
                        if (isset($hooks['get'])) {
                            $child->hooks |= Property_Context::HOOK_GET;
                            if ($hooks['get']->returns_reference()) {
                                $child->hooks |= Property_Context::HOOK_GET_REF;
                            }
                        }
                        if (isset($hooks['set'])) {
                            $child->hooks |= Property_Context::HOOK_SET;
                            $child->hook_set_type = (string) $rprop->get_settable_type();
                            if ($child->hook_set_type !== (string) $rprop->get_type()) {
                                $child->hooks |= Property_Context::HOOK_SET_TYPE;
                            } elseif ('' === $child->hook_set_type) {
                                $child->hook_set_type = null;
                            }
                        }
                    }
                    if (KINT_PHP8412) {
                        $proto_prop = $rprop;
                        while (($parent_class = $proto_prop->get_declaring_class()->get_parent_class()) && $parent_class->has_property($name) && ($parent_prop = $parent_class->get_property($name)) && !$parent_prop->is_private()) {
                            $proto_prop = $parent_prop;
                        }
                        $proto_class = $proto_prop->get_declaring_class()->get_name();
                        if ($proto_class !== $child->owner_class) {
                            $child->proto_class = $proto_prop->get_declaring_class()->get_name();
                        }
                    }
                } else {
                    $child = new Class_Owned_Context($name, $rprop->get_declaring_class()->get_name());
                }
                $child->reference = $initialized && null !== Reflection_Reference::from_array_element($values, $key);
                $child->depth = $cdepth + 1;
                if (null !== $ap && $child->is_accessible($this->caller_class)) {
                    /** @psalm-var string $child->name */
                    if (Utils::is_valid_php_name($child->name)) {
                        $child->access_path = $ap . '->' . $child->name;
                    } else {
                        $child->access_path = $ap . '->{' . \var_export($child->name, true) . '}';
                    }
                }
                if (KINT_PHP84 && $rprop->is_virtual()) {
                    $properties[] = new Virtual_Value($child);
                } elseif (!$initialized) {
                    $properties[] = new Uninitialized_Value($child);
                } else {
                    $properties[] = $this->parse($values[$key], $child);
                }
            }
            $object = new Instance_Value($c, $classname, $hash, \spl_object_id($var));
            if ($props) {
                $object->set_children($properties);
            }
            if ($properties) {
                $object->add_representation(new Container_Representation('Properties', $properties));
            }
            return $this->apply_plugins_complete($var, $object, self::TRIGGER_SUCCESS);
        } finally {
            unset($this->object_hashes[$hash]);
        }
    }
    /**
     * @psalm-param resource $var
     */
    private function parse_resource(&$var, Context_Interface $c): Abstract_Value
    {
        $resource = new Resource_Value($c, \get_resource_type($var));
        $resource = $this->apply_plugins_complete($var, $resource, self::TRIGGER_SUCCESS);
        return $resource;
    }
    /**
     * @psalm-param mixed $var
     */
    private function parse_resource_closed(&$var, Context_Interface $c): Abstract_Value
    {
        $v = new Closed_Resource_Value($c);
        $v = $this->apply_plugins_complete($var, $v, self::TRIGGER_SUCCESS);
        return $v;
    }
    /**
     * Catch-all for any unexpectedgettype.
     *
     * This should never happen.
     *
     * @codeCoverageIgnore
     *
     * @psalm-param mixed $var
     */
    private function parse_unknown(&$var, Context_Interface $c): Abstract_Value
    {
        $v = new Unknown_Value($c);
        $v = $this->apply_plugins_complete($var, $v, self::TRIGGER_SUCCESS);
        return $v;
    }
    /**
     * Applies plugins for a yet-unparsed value.
     *
     * @param mixed &$var The input variable
     */
    private function apply_plugins_begin(&$var, Context_Interface $c, string $type): ?Abstract_Value
    {
        $plugins = $this->plugins[$type][self::TRIGGER_BEGIN] ?? [];
        foreach ($plugins as $plugin) {
            try {
                if ($v = $plugin->parse_begin($var, $c)) {
                    return $v;
                }
            } catch (Throwable $e) {
                \trigger_error(Utils::error_sanitize_string(\get_class($e)) . ' was thrown in ' . $e->get_file() . ' on line ' . $e->get_line() . ' while executing ' . Utils::error_sanitize_string(\get_class($plugin)) . '->parseBegin. Error message: ' . Utils::error_sanitize_string($e->get_message()), E_USER_WARNING);
            }
        }
        return null;
    }
    /**
     * Applies plugins for a parsed AbstractValue.
     *
     * @param mixed &$var The input variable
     */
    private function apply_plugins_complete(&$var, Abstract_Value $v, int $trigger): Abstract_Value
    {
        $plugins = $this->plugins[$v->get_type()][$trigger] ?? [];
        foreach ($plugins as $plugin) {
            try {
                $v = $plugin->parse_complete($var, $v, $trigger);
            } catch (Throwable $e) {
                \trigger_error(Utils::error_sanitize_string(\get_class($e)) . ' was thrown in ' . $e->get_file() . ' on line ' . $e->get_line() . ' while executing ' . Utils::error_sanitize_string(\get_class($plugin)) . '->parseComplete. Error message: ' . Utils::error_sanitize_string($e->get_message()), E_USER_WARNING);
            }
        }
        return $v;
    }
}