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
namespace Code_Igniter\Config;

use Code_Igniter\Autoloader\File_Locator_Interface;
use Code_Igniter\Exceptions\Config_Exception;
use Code_Igniter\Exceptions\RuntimeException;
use Config\Encryption;
use Config\Modules;
use ReflectionClass;
use Reflection_Exception;
/**
 * Class BaseConfig
 *
 * Not intended to be used on its own, this class will attempt to
 * automatically populate the child class' properties with values
 * from the environment.
 *
 * These can be set within the .env file.
 *
 * @phpstan-consistent-constructor
 * @see \CodeIgniter\Config\BaseConfigTest
 */
class Base_Config
{
    /**
     * An optional array of classes that will act as Registrars
     * for rapidly setting config class properties.
     *
     * @var array
     */
    public static $registrars = [];
    /**
     * Whether to override properties by Env vars and Registrars.
     */
    public static bool $override = true;
    /**
     * Has module discovery completed?
     *
     * @var bool
     */
    protected static $did_discovery = false;
    /**
     * Is module discovery running or not?
     */
    protected static bool $discovering = false;
    /**
     * The processing Registrar file for error message.
     */
    protected static string $registrar_file = '';
    /**
     * The modules configuration.
     *
     * @var Modules|null
     */
    protected static $module_config;
    public static function __set_state(array $array)
    {
        static::$override = false;
        $obj = new static();
        static::$override = true;
        $properties = array_keys(get_object_vars($obj));
        foreach ($properties as $property) {
            $obj->{$property} = $array[$property];
        }
        return $obj;
    }
    /**
     * @internal For testing purposes only.
     * @testTag
     */
    public static function set_modules(Modules $modules): void
    {
        static::$module_config = $modules;
    }
    /**
     * @internal For testing purposes only.
     * @testTag
     */
    public static function reset(): void
    {
        static::$registrars = [];
        static::$override = true;
        static::$did_discovery = false;
        static::$module_config = null;
    }
    /**
     * Will attempt to get environment variables with names
     * that match the properties of the child class.
     *
     * The "shortPrefix" is the lowercase-only config class name.
     */
    public function __construct()
    {
        static::$module_config ??= new Modules();
        if (!static::$override) {
            return;
        }
        $this->register_properties();
        $properties = array_keys(get_object_vars($this));
        $prefix = static::class;
        $slash_at = strrpos($prefix, '\\');
        $short_prefix = strtolower(substr($prefix, $slash_at === false ? 0 : $slash_at + 1));
        foreach ($properties as $property) {
            $this->init_env_value($this->{$property}, $property, $prefix, $short_prefix);
            if ($this instanceof Encryption) {
                if ($property === 'key') {
                    $this->{$property} = $this->parse_encryption_key($this->{$property});
                } elseif ($property === 'previousKeys') {
                    $keys_array = is_string($this->{$property}) ? array_map(trim(...), explode(',', $this->{$property})) : $this->{$property};
                    $parsed_keys = [];
                    foreach ($keys_array as $key) {
                        $parsed_keys[] = $this->parse_encryption_key($key);
                    }
                    $this->{$property} = $parsed_keys;
                }
            }
        }
    }
    /**
     * Parse encryption key with hex2bin: or base64: prefix
     */
    protected function parse_encryption_key(string $key): string
    {
        if (str_starts_with($key, 'hex2bin:')) {
            return hex2bin(substr($key, 8));
        }
        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7), true);
        }
        return $key;
    }
    /**
     * Initialization an environment-specific configuration setting
     *
     * @param array|bool|float|int|string|null $property
     *
     * @return void
     */
    protected function init_env_value(&$property, string $name, string $prefix, string $short_prefix)
    {
        if (is_array($property)) {
            foreach (array_keys($property) as $key) {
                $this->init_env_value($property[$key], "{$name}.{$key}", $prefix, $short_prefix);
            }
        } elseif (($value = $this->get_env_value($name, $prefix, $short_prefix)) !== false && $value !== null) {
            if ($value === 'false') {
                $value = false;
            } elseif ($value === 'true') {
                $value = true;
            }
            if (is_bool($value)) {
                $property = $value;
                return;
            }
            $value = trim($value, '\'"');
            if (is_int($property)) {
                $value = (int) $value;
            } elseif (is_float($property)) {
                $value = (float) $value;
            }
            // If the default value of the property is `null` and the type is not
            // `string`, TypeError will happen.
            // So cannot set `declare(strict_types=1)` in this file.
            $property = $value;
        }
    }
    /**
     * Retrieve an environment-specific configuration setting
     *
     * @return string|null
     */
    protected function get_env_value(string $property, string $prefix, string $short_prefix)
    {
        $short_prefix = ltrim($short_prefix, '\\');
        $underscore_property = str_replace('.', '_', $property);
        switch (true) {
            case array_key_exists("{$short_prefix}.{$property}", $_ENV):
                return $_ENV["{$short_prefix}.{$property}"];
            case array_key_exists("{$short_prefix}_{$underscore_property}", $_ENV):
                return $_ENV["{$short_prefix}_{$underscore_property}"];
            case array_key_exists("{$short_prefix}.{$property}", $_SERVER):
                return $_SERVER["{$short_prefix}.{$property}"];
            case array_key_exists("{$short_prefix}_{$underscore_property}", $_SERVER):
                return $_SERVER["{$short_prefix}_{$underscore_property}"];
            case array_key_exists("{$prefix}.{$property}", $_ENV):
                return $_ENV["{$prefix}.{$property}"];
            case array_key_exists("{$prefix}_{$underscore_property}", $_ENV):
                return $_ENV["{$prefix}_{$underscore_property}"];
            case array_key_exists("{$prefix}.{$property}", $_SERVER):
                return $_SERVER["{$prefix}.{$property}"];
            case array_key_exists("{$prefix}_{$underscore_property}", $_SERVER):
                return $_SERVER["{$prefix}_{$underscore_property}"];
            default:
                $value = getenv("{$short_prefix}.{$property}");
                $value = $value === false ? getenv("{$short_prefix}_{$underscore_property}") : $value;
                $value = $value === false ? getenv("{$prefix}.{$property}") : $value;
                $value = $value === false ? getenv("{$prefix}_{$underscore_property}") : $value;
                return $value === false ? null : $value;
        }
    }
    /**
     * Provides external libraries a simple way to register one or more
     * options into a config file.
     *
     * @return void
     *
     * @throws ReflectionException
     */
    protected function register_properties()
    {
        if (!static::$module_config->should_discover('registrars')) {
            return;
        }
        if (!static::$did_discovery) {
            // Discovery must be completed before the first instantiation of any Config class.
            if (static::$discovering) {
                throw new Config_Exception('During Auto-Discovery of Registrars,' . ' "' . static::class . '" executes Auto-Discovery again.' . ' "' . clean_path(static::$registrar_file) . '" seems to have bad code.');
            }
            static::$discovering = true;
            /** @var FileLocatorInterface */
            $locator = service('locator');
            $registrars_files = $locator->search('Config/Registrar.php');
            foreach ($registrars_files as $file) {
                // Saves the file for error message.
                static::$registrar_file = $file;
                $class_name = $locator->find_qualified_name_from_path($file);
                if ($class_name === false) {
                    continue;
                }
                static::$registrars[] = new $class_name();
            }
            static::$did_discovery = true;
            static::$discovering = false;
        }
        $short_name = (new ReflectionClass($this))->get_short_name();
        // Check the registrar class for a method named after this class' shortName
        foreach (static::$registrars as $callable) {
            // ignore non-applicable registrars
            if (!method_exists($callable, $short_name)) {
                continue;
                // @codeCoverageIgnore
            }
            $properties = $callable::$short_name();
            if (!is_array($properties)) {
                throw new RuntimeException('Registrars must return an array of properties and their values.');
            }
            foreach ($properties as $property => $value) {
                if (isset($this->{$property}) && is_array($this->{$property}) && is_array($value)) {
                    $this->{$property} = array_merge($this->{$property}, $value);
                } else {
                    $this->{$property} = $value;
                }
            }
        }
    }
}