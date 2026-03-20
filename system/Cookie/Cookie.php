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
namespace Code_Igniter\Cookie;

use ArrayAccess;
use Code_Igniter\Cookie\Exceptions\Cookie_Exception;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\Exceptions\LogicException;
use Code_Igniter\I18n\Time;
use Config\Cookie as CookieConfig;
use DateTimeInterface;
/**
 * A `Cookie` class represents an immutable HTTP cookie value object.
 *
 * Being immutable, modifying one or more of its attributes will return
 * a new `Cookie` instance, rather than modifying itself. Users should
 * reassign this new instance to a new variable to capture it.
 *
 * ```php
 * $cookie = new Cookie('test_cookie', 'test_value');
 * $cookie->getName(); // test_cookie
 *
 * $cookie->withName('prod_cookie');
 * $cookie->getName(); // test_cookie
 *
 * $cookie2 = $cookie->withName('prod_cookie');
 * $cookie2->getName(); // prod_cookie
 * ```
 *
 * @template-implements ArrayAccess<string, bool|int|string>
 * @see \CodeIgniter\Cookie\CookieTest
 */
class Cookie implements ArrayAccess, Cloneable_Cookie_Interface
{
    /**
     * @var string
     */
    protected $prefix = '';
    /**
     * @var string
     */
    protected $name;
    /**
     * @var string
     */
    protected $value;
    /**
     * @var int Unix timestamp
     */
    protected $expires;
    /**
     * @var string
     */
    protected $path = '/';
    /**
     * @var string
     */
    protected $domain = '';
    /**
     * @var bool
     */
    protected $secure = false;
    /**
     * @var bool
     */
    protected $httponly = true;
    /**
     * @var string
     */
    protected $samesite = self::SAMESITE_LAX;
    /**
     * @var bool
     */
    protected $raw = false;
    /**
     * Default attributes for a Cookie object. The keys here are the
     * lowercase attribute names. Do not camelCase!
     *
     * @var array{
     *  prefix: string,
     *  expires: int,
     *  path: string,
     *  domain: string,
     *  secure: bool,
     *  httponly: bool,
     *  samesite: string,
     *  raw: bool,
     * }
     */
    private static array $defaults = ['prefix' => '', 'expires' => 0, 'path' => '/', 'domain' => '', 'secure' => false, 'httponly' => true, 'samesite' => self::SAMESITE_LAX, 'raw' => false];
    /**
     * A cookie name can be any US-ASCII characters, except control characters,
     * spaces, tabs, or separator characters.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#attributes
     * @see https://tools.ietf.org/html/rfc2616#section-2.2
     */
    private static string $reserved_chars_list = "=,; \t\r\n\v\f()<>@:\\\"/[]?{}";
    /**
     * Set the default attributes to a Cookie instance by injecting
     * the values from the `CookieConfig` config or an array.
     *
     * This method is called from Response::__construct().
     *
     * @param array{
     *  prefix?: string,
     *  expires?: int,
     *  path?: string,
     *  domain?: string,
     *  secure?: bool,
     *  httponly?: bool,
     *  samesite?: string,
     *  raw?: bool,
     * }|CookieConfig $config
     *
     * @return array{
     *  prefix: string,
     *  expires: int,
     *  path: string,
     *  domain: string,
     *  secure: bool,
     *  httponly: bool,
     *  samesite: string,
     *  raw: bool,
     * } The old defaults array. Useful for resetting.
     */
    public static function set_defaults($config = [])
    {
        $old_defaults = self::$defaults;
        $new_defaults = [];
        if ($config instanceof Cookie_Config) {
            $new_defaults = ['prefix' => $config->prefix, 'expires' => $config->expires, 'path' => $config->path, 'domain' => $config->domain, 'secure' => $config->secure, 'httponly' => $config->httponly, 'samesite' => $config->samesite, 'raw' => $config->raw];
        } elseif (is_array($config)) {
            $new_defaults = $config;
        }
        // This array union ensures that even if passed `$config` is not
        // `CookieConfig` or `array`, no empty defaults will occur.
        self::$defaults = $new_defaults + $old_defaults;
        return $old_defaults;
    }
    // =========================================================================
    // CONSTRUCTORS
    // =========================================================================
    /**
     * Create a new Cookie instance from a `Set-Cookie` header.
     *
     * @return static
     *
     * @throws CookieException
     */
    public static function from_header_string(string $cookie, bool $raw = false)
    {
        $data = self::$defaults;
        $data['raw'] = $raw;
        $parts = preg_split('/\;[\s]*/', $cookie);
        $part = explode('=', array_shift($parts), 2);
        $name = $raw ? $part[0] : urldecode($part[0]);
        $value = isset($part[1]) ? $raw ? $part[1] : urldecode($part[1]) : '';
        unset($part);
        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$attr, $val] = explode('=', $part);
            } else {
                $attr = $part;
                $val = true;
            }
            $data[strtolower($attr)] = $val;
        }
        return new static($name, $value, $data);
    }
    /**
     * Construct a new Cookie instance.
     *
     * @param string $name  The cookie's name
     * @param string $value The cookie's value
     * @param array{
     *   prefix?: string,
     *   max-age?: int|numeric-string,
     *   expires?: DateTimeInterface|int|string,
     *   path?: string,
     *   domain?: string,
     *   secure?: bool,
     *   httponly?: bool,
     *   samesite?: string,
     *   raw?: bool,
     * } $options The cookie's options
     *
     * @throws CookieException
     */
    final public function __construct(string $name, string $value = '', array $options = [])
    {
        $options += self::$defaults;
        $options['expires'] = static::convert_expires_timestamp($options['expires']);
        // If both `Expires` and `Max-Age` are set, `Max-Age` has precedence.
        if (isset($options['max-age']) && is_numeric($options['max-age'])) {
            $options['expires'] = Time::now()->get_timestamp() + (int) $options['max-age'];
            unset($options['max-age']);
        }
        // to preserve backward compatibility with array-based cookies in previous CI versions
        $prefix = $options['prefix'] === '' ? self::$defaults['prefix'] : $options['prefix'];
        $path = $options['path'] ?: self::$defaults['path'];
        $domain = $options['domain'] ?: self::$defaults['domain'];
        // empty string SameSite should use the default for browsers
        $samesite = $options['samesite'] ?: self::$defaults['samesite'];
        $raw = $options['raw'];
        $secure = $options['secure'];
        $httponly = $options['httponly'];
        $this->validate_name($name, $raw);
        $this->validate_prefix($prefix, $secure, $path, $domain);
        $this->validate_same_site($samesite, $secure);
        $this->prefix = $prefix;
        $this->name = $name;
        $this->value = $value;
        $this->expires = static::convert_expires_timestamp($options['expires']);
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->httponly = $httponly;
        $this->samesite = ucfirst(strtolower($samesite));
        $this->raw = $raw;
    }
    // =========================================================================
    // GETTERS
    // =========================================================================
    /**
     * {@inheritDoc}
     */
    public function get_id(): string
    {
        return implode(';', [$this->get_prefixed_name(), $this->get_path(), $this->get_domain()]);
    }
    /**
     * {@inheritDoc}
     */
    public function get_prefix(): string
    {
        return $this->prefix;
    }
    /**
     * {@inheritDoc}
     */
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * {@inheritDoc}
     */
    public function get_prefixed_name(): string
    {
        $name = $this->get_prefix();
        if ($this->is_raw()) {
            $name .= $this->get_name();
        } else {
            $search = str_split(self::$reserved_chars_list);
            $replace = array_map(rawurlencode(...), $search);
            $name .= str_replace($search, $replace, $this->get_name());
        }
        return $name;
    }
    /**
     * {@inheritDoc}
     */
    public function get_value(): string
    {
        return $this->value;
    }
    /**
     * {@inheritDoc}
     */
    public function get_expires_timestamp(): int
    {
        return $this->expires;
    }
    /**
     * {@inheritDoc}
     */
    public function get_expires_string(): string
    {
        return gmdate(self::EXPIRES_FORMAT, $this->expires);
    }
    /**
     * {@inheritDoc}
     */
    public function is_expired(): bool
    {
        return $this->expires === 0 || $this->expires < Time::now()->get_timestamp();
    }
    /**
     * {@inheritDoc}
     */
    public function get_max_age(): int
    {
        $max_age = $this->expires - Time::now()->get_timestamp();
        return $max_age >= 0 ? $max_age : 0;
    }
    /**
     * {@inheritDoc}
     */
    public function get_path(): string
    {
        return $this->path;
    }
    /**
     * {@inheritDoc}
     */
    public function get_domain(): string
    {
        return $this->domain;
    }
    /**
     * {@inheritDoc}
     */
    public function is_secure(): bool
    {
        return $this->secure;
    }
    /**
     * {@inheritDoc}
     */
    public function is_http_only(): bool
    {
        return $this->httponly;
    }
    /**
     * {@inheritDoc}
     */
    public function get_same_site(): string
    {
        return $this->samesite;
    }
    /**
     * {@inheritDoc}
     */
    public function is_raw(): bool
    {
        return $this->raw;
    }
    /**
     * {@inheritDoc}
     */
    public function get_options(): array
    {
        // This is the order of options in `setcookie`. DO NOT CHANGE.
        return ['expires' => $this->expires, 'path' => $this->path, 'domain' => $this->domain, 'secure' => $this->secure, 'httponly' => $this->httponly, 'samesite' => $this->samesite ?: ucfirst(self::SAMESITE_LAX)];
    }
    // =========================================================================
    // CLONING
    // =========================================================================
    /**
     * {@inheritDoc}
     */
    public function with_prefix(string $prefix = '')
    {
        $this->validate_prefix($prefix, $this->secure, $this->path, $this->domain);
        $cookie = clone $this;
        $cookie->prefix = $prefix;
        return $cookie;
    }
    /**
     * {@inheritDoc}
     */
    public function with_name(string $name)
    {
        $this->validate_name($name, $this->raw);
        $cookie = clone $this;
        $cookie->name = $name;
        return $cookie;
    }
    /**
     * {@inheritDoc}
     */
    public function with_value(string $value)
    {
        $cookie = clone $this;
        $cookie->value = $value;
        return $cookie;
    }
    /**
     * {@inheritDoc}
     */
    public function with_expires($expires)
    {
        $cookie = clone $this;
        $cookie->expires = static::convert_expires_timestamp($expires);
        return $cookie;
    }
    /**
     * {@inheritDoc}
     */
    public function with_expired()
    {
        $cookie = clone $this;
        $cookie->expires = 0;
        return $cookie;
    }
    /**
     * {@inheritDoc}
     */
    public function with_path(?string $path)
    {
        $path = in_array($path, [null, '', '0'], true) ? self::$defaults['path'] : $path;
        $this->validate_prefix($this->prefix, $this->secure, $path, $this->domain);
        $cookie = clone $this;
        $cookie->path = $path;
        return $cookie;
    }
    /**
     * {@inheritDoc}
     */
    public function with_domain(?string $domain)
    {
        $domain ??= self::$defaults['domain'];
        $this->validate_prefix($this->prefix, $this->secure, $this->path, $domain);
        $cookie = clone $this;
        $cookie->domain = $domain;
        return $cookie;
    }
    /**
     * {@inheritDoc}
     */
    public function with_secure(bool $secure = true)
    {
        $this->validate_prefix($this->prefix, $secure, $this->path, $this->domain);
        $this->validate_same_site($this->samesite, $secure);
        $cookie = clone $this;
        $cookie->secure = $secure;
        return $cookie;
    }
    /**
     * {@inheritDoc}
     */
    public function with_http_only(bool $httponly = true)
    {
        $cookie = clone $this;
        $cookie->httponly = $httponly;
        return $cookie;
    }
    /**
     * {@inheritDoc}
     */
    public function with_same_site(string $samesite)
    {
        $this->validate_same_site($samesite, $this->secure);
        $cookie = clone $this;
        $cookie->samesite = ucfirst(strtolower($samesite));
        return $cookie;
    }
    /**
     * {@inheritDoc}
     */
    public function with_raw(bool $raw = true)
    {
        $this->validate_name($this->name, $raw);
        $cookie = clone $this;
        $cookie->raw = $raw;
        return $cookie;
    }
    // =========================================================================
    // ARRAY ACCESS FOR BC
    // =========================================================================
    /**
     * Whether an offset exists.
     *
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return $offset === 'expire' ? true : property_exists($this, $offset);
    }
    /**
     * Offset to retrieve.
     *
     * @param string $offset
     *
     * @throws InvalidArgumentException
     */
    public function offsetGet($offset): bool|int|string
    {
        if (!$this->offsetExists($offset)) {
            throw new InvalidArgumentException(sprintf('Undefined offset "%s".', $offset));
        }
        return $offset === 'expire' ? $this->expires : $this->{$offset};
    }
    /**
     * Offset to set.
     *
     * @param string          $offset
     * @param bool|int|string $value
     *
     * @throws LogicException
     */
    public function offsetSet($offset, $value): void
    {
        throw new LogicException(sprintf('Cannot set values of properties of %s as it is immutable.', static::class));
    }
    /**
     * Offset to unset.
     *
     * @param string $offset
     *
     * @throws LogicException
     */
    public function offsetUnset($offset): void
    {
        throw new LogicException(sprintf('Cannot unset values of properties of %s as it is immutable.', static::class));
    }
    // =========================================================================
    // CONVERTERS
    // =========================================================================
    /**
     * {@inheritDoc}
     */
    public function to_header_string(): string
    {
        return $this->__toString();
    }
    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        $cookie_header = [];
        if ($this->get_value() === '') {
            $cookie_header[] = $this->get_prefixed_name() . '=deleted';
            $cookie_header[] = 'Expires=' . gmdate(self::EXPIRES_FORMAT, 0);
            $cookie_header[] = 'Max-Age=0';
        } else {
            $value = $this->is_raw() ? $this->get_value() : rawurlencode($this->get_value());
            $cookie_header[] = sprintf('%s=%s', $this->get_prefixed_name(), $value);
            if ($this->get_expires_timestamp() !== 0) {
                $cookie_header[] = 'Expires=' . $this->get_expires_string();
                $cookie_header[] = 'Max-Age=' . $this->get_max_age();
            }
        }
        if ($this->get_path() !== '') {
            $cookie_header[] = 'Path=' . $this->get_path();
        }
        if ($this->get_domain() !== '') {
            $cookie_header[] = 'Domain=' . $this->get_domain();
        }
        if ($this->is_secure()) {
            $cookie_header[] = 'Secure';
        }
        if ($this->is_http_only()) {
            $cookie_header[] = 'HttpOnly';
        }
        $samesite = $this->get_same_site();
        if ($samesite === '') {
            // modern browsers warn in console logs that an empty SameSite attribute
            // will be given the `Lax` value
            $samesite = self::SAMESITE_LAX;
        }
        $cookie_header[] = 'SameSite=' . ucfirst(strtolower($samesite));
        return implode('; ', $cookie_header);
    }
    /**
     * {@inheritDoc}
     */
    public function to_array(): array
    {
        return ['name' => $this->name, 'value' => $this->value, 'prefix' => $this->prefix, 'raw' => $this->raw] + $this->get_options();
    }
    /**
     * Converts expires time to Unix format.
     *
     * @param DateTimeInterface|int|string $expires
     */
    protected static function convert_expires_timestamp($expires = 0): int
    {
        if ($expires instanceof DateTimeInterface) {
            $expires = $expires->format('U');
        }
        if (!is_string($expires) && !is_int($expires)) {
            throw Cookie_Exception::for_invalid_expires_time(gettype($expires));
        }
        if (!is_numeric($expires)) {
            $expires = strtotime($expires);
            if ($expires === false) {
                throw Cookie_Exception::for_invalid_expires_value();
            }
        }
        return $expires > 0 ? (int) $expires : 0;
    }
    // =========================================================================
    // VALIDATION
    // =========================================================================
    /**
     * Validates the cookie name per RFC 2616.
     *
     * If `$raw` is true, names should not contain invalid characters
     * as `setrawcookie()` will reject this.
     *
     * @throws CookieException
     */
    protected function validate_name(string $name, bool $raw): void
    {
        if ($raw && strpbrk($name, self::$reserved_chars_list) !== false) {
            throw Cookie_Exception::for_invalid_cookie_name($name);
        }
        if ($name === '') {
            throw Cookie_Exception::for_empty_cookie_name();
        }
    }
    /**
     * Validates the special prefixes if some attribute requirements are met.
     *
     * @throws CookieException
     */
    protected function validate_prefix(string $prefix, bool $secure, string $path, string $domain): void
    {
        if (str_starts_with($prefix, '__Secure-') && !$secure) {
            throw Cookie_Exception::for_invalid_secure_prefix();
        }
        if (str_starts_with($prefix, '__Host-') && (!$secure || $domain !== '' || $path !== '/')) {
            throw Cookie_Exception::for_invalid_host_prefix();
        }
    }
    /**
     * Validates the `SameSite` to be within the allowed types.
     *
     * @throws CookieException
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie/SameSite
     */
    protected function validate_same_site(string $samesite, bool $secure): void
    {
        if ($samesite === '') {
            $samesite = self::$defaults['samesite'];
        }
        if ($samesite === '') {
            $samesite = self::SAMESITE_LAX;
        }
        if (!in_array(ucfirst(strtolower($samesite)), self::ALLOWED_SAMESITE_VALUES, true)) {
            throw Cookie_Exception::for_invalid_same_site($samesite);
        }
        if (ucfirst(strtolower($samesite)) === self::SAMESITE_NONE && !$secure) {
            throw Cookie_Exception::for_invalid_same_site_none();
        }
    }
}