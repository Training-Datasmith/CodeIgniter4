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
namespace Code_Igniter\HTTP;

use Code_Igniter\Exceptions\Config_Exception;
use Code_Igniter\Validation\Format_Rules;
use Config\App;
/**
 * Request Trait
 *
 * Additional methods to make a PSR-7 Request class
 * compliant with the framework's own RequestInterface.
 *
 * @see https://github.com/php-fig/http-message/blob/master/src/RequestInterface.php
 */
trait Request_Trait
{
    /**
     * Configuration settings.
     *
     * @var App
     */
    protected $config;
    /**
     * IP address of the current user.
     *
     * @var string
     *
     * @deprecated Will become private in a future release
     */
    protected $ip_address = '';
    /**
     * Stores values we've retrieved from PHP globals.
     *
     * @var array{get?: array, post?: array, request?: array, cookie?: array, server?: array}
     *
     * @deprecated 4.7.0 Use the Superglobals service instead
     */
    protected $globals = [];
    /**
     * Gets the user's IP address.
     *
     * @return string IP address if it can be detected.
     *                If the IP address is not a valid IP address,
     *                then will return '0.0.0.0'.
     */
    public function get_ip_address(): string
    {
        if ($this->ip_address !== '') {
            return $this->ip_address;
        }
        $ip_validator = [new Format_Rules(), 'valid_ip'];
        $proxy_i_ps = $this->config->proxy_i_ps;
        if (!empty($proxy_i_ps) && (!is_array($proxy_i_ps) || is_int(array_key_first($proxy_i_ps)))) {
            throw new Config_Exception('You must set an array with Proxy IP address key and HTTP header name value in Config\App::$proxyIPs.');
        }
        $this->ip_address = $this->get_server('REMOTE_ADDR');
        // If this is a CLI request, $this->ipAddress is null.
        if ($this->ip_address === null) {
            return $this->ip_address = '0.0.0.0';
        }
        // @TODO Extract all this IP address logic to another class.
        foreach ($proxy_i_ps as $proxy_ip => $header) {
            // Check if we have an IP address or a subnet
            if (!str_contains($proxy_ip, '/')) {
                // An IP address (and not a subnet) is specified.
                // We can compare right away.
                if ($proxy_ip === $this->ip_address) {
                    $spoof = $this->get_client_ip($header);
                    if ($spoof !== null) {
                        $this->ip_address = $spoof;
                        break;
                    }
                }
                continue;
            }
            // We have a subnet ... now the heavy lifting begins
            if (!isset($separator)) {
                $separator = $ip_validator($this->ip_address, 'ipv6') ? ':' : '.';
            }
            // If the proxy entry doesn't match the IP protocol - skip it
            if (!str_contains($proxy_ip, $separator)) {
                continue;
            }
            // Convert the REMOTE_ADDR IP address to binary, if needed
            if (!isset($ip, $sprintf)) {
                if ($separator === ':') {
                    // Make sure we're having the "full" IPv6 format
                    $ip = explode(':', str_replace('::', str_repeat(':', 9 - substr_count($this->ip_address, ':')), $this->ip_address));
                    for ($j = 0; $j < 8; $j++) {
                        $ip[$j] = intval($ip[$j], 16);
                    }
                    $sprintf = '%016b%016b%016b%016b%016b%016b%016b%016b';
                } else {
                    $ip = explode('.', $this->ip_address);
                    $sprintf = '%08b%08b%08b%08b';
                }
                $ip = vsprintf($sprintf, $ip);
            }
            // Split the netmask length off the network address
            sscanf($proxy_ip, '%[^/]/%d', $netaddr, $masklen);
            // Again, an IPv6 address is most likely in a compressed form
            if ($separator === ':') {
                $netaddr = explode(':', str_replace('::', str_repeat(':', 9 - substr_count($netaddr, ':')), $netaddr));
                for ($i = 0; $i < 8; $i++) {
                    $netaddr[$i] = intval($netaddr[$i], 16);
                }
            } else {
                $netaddr = explode('.', $netaddr);
            }
            // Convert to binary and finally compare
            if (strncmp($ip, vsprintf($sprintf, $netaddr), $masklen) === 0) {
                $spoof = $this->get_client_ip($header);
                if ($spoof !== null) {
                    $this->ip_address = $spoof;
                    break;
                }
            }
        }
        if (!$ip_validator($this->ip_address)) {
            return $this->ip_address = '0.0.0.0';
        }
        return $this->ip_address;
    }
    /**
     * Gets the client IP address from the HTTP header.
     */
    private function get_client_ip(string $header): ?string
    {
        $ip_validator = [new Format_Rules(), 'valid_ip'];
        $spoof = null;
        $header_obj = $this->header($header);
        if ($header_obj !== null) {
            $spoof = $header_obj->get_value();
            // Some proxies typically list the whole chain of IP
            // addresses through which the client has reached us.
            // e.g. client_ip, proxy_ip1, proxy_ip2, etc.
            sscanf($spoof, '%[^,]', $spoof);
            if (!$ip_validator($spoof)) {
                $spoof = null;
            }
        }
        return $spoof;
    }
    /**
     * Fetch an item from the $_SERVER array.
     *
     * @param array|string|null $index  Index for item to be fetched from $_SERVER
     * @param int|null          $filter A filter name to be applied
     * @param array|int|null    $flags
     *
     * @return mixed
     */
    public function get_server($index = null, $filter = null, $flags = null)
    {
        return $this->fetch_global('server', $index, $filter, $flags);
    }
    /**
     * Fetch an item from the $_ENV array.
     *
     * @param array|string|null $index  Index for item to be fetched from $_ENV
     * @param int|null          $filter A filter name to be applied
     * @param array|int|null    $flags
     *
     * @return mixed
     *
     * @deprecated 4.4.4 This method does not work from the beginning. Use `env()`.
     */
    public function get_env($index = null, $filter = null, $flags = null)
    {
        // @phpstan-ignore-next-line
        return $this->fetch_global('env', $index, $filter, $flags);
    }
    /**
     * Allows manually setting the value of PHP global, like $_GET, $_POST, etc.
     *
     * @param 'cookie'|'get'|'post'|'request'|'server' $name  Superglobal name (lowercase)
     * @param mixed                                    $value
     *
     * @return $this
     */
    public function set_global(string $name, $value)
    {
        // Keep BC with $globals array
        $this->globals[$name] = $value;
        // Also update Superglobals via service
        service('superglobals')->set_global_array($name, $value);
        return $this;
    }
    /**
     * Fetches one or more items from a global, like cookies, get, post, etc.
     * Can optionally filter the input when you retrieve it by passing in
     * a filter.
     *
     * If $type is an array, it must conform to the input allowed by the
     * filter_input_array method.
     *
     * http://php.net/manual/en/filter.filters.sanitize.php
     *
     * @param 'cookie'|'get'|'post'|'request'|'server' $name   Superglobal name (lowercase)
     * @param array|int|string|null                    $index
     * @param int|null                                 $filter Filter constant
     * @param array|int|null                           $flags  Options
     *
     * @return array|bool|float|int|object|string|null
     */
    public function fetch_global(string $name, $index = null, ?int $filter = null, $flags = null)
    {
        if (!isset($this->globals[$name])) {
            $this->populate_globals($name);
        }
        // Null filters cause null values to return.
        $filter ??= FILTER_UNSAFE_RAW;
        $flags = is_array($flags) ? $flags : (is_numeric($flags) ? (int) $flags : 0);
        // Return all values when $index is null
        if ($index === null) {
            $values = [];
            foreach ($this->globals[$name] as $key => $value) {
                $values[$key] = is_array($value) ? $this->fetch_global($name, $key, $filter, $flags) : filter_var($value, $filter, $flags);
            }
            return $values;
        }
        // allow fetching multiple keys at once
        if (is_array($index)) {
            $output = [];
            foreach ($index as $key) {
                $output[$key] = $this->fetch_global($name, $key, $filter, $flags);
            }
            return $output;
        }
        // Does the index contain array notation?
        if (is_string($index) && ($count = preg_match_all('/(?:^[^\[]+)|\[[^]]*\]/', $index, $matches)) > 1) {
            $value = $this->globals[$name];
            for ($i = 0; $i < $count; $i++) {
                $key = trim($matches[0][$i], '[]');
                if ($key === '') {
                    // Empty notation will return the value as array
                    break;
                }
                if (isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    return null;
                }
            }
        }
        if (!isset($value)) {
            $value = $this->globals[$name][$index] ?? null;
        }
        if (is_array($value) && ($filter !== FILTER_UNSAFE_RAW || (is_numeric($flags) && $flags !== 0 || is_array($flags) && $flags !== []))) {
            // Iterate over array and append filter and flags
            array_walk_recursive($value, static function (&$val) use ($filter, $flags): void {
                $val = filter_var($val, $filter, $flags);
            });
            return $value;
        }
        // Cannot filter these types of data automatically...
        if (is_array($value) || is_object($value) || $value === null) {
            return $value;
        }
        return filter_var($value, $filter, $flags);
    }
    /**
     * Saves a copy of the current state of one of several PHP globals,
     * so we can retrieve them later.
     *
     * @param 'cookie'|'get'|'post'|'request'|'server' $name Superglobal name (lowercase)
     *
     * @return void
     *
     * @deprecated 4.7.0 No longer needs to be called explicitly. Used internally to maintain BC with $globals.
     */
    protected function populate_globals(string $name)
    {
        if (!isset($this->globals[$name])) {
            $this->globals[$name] = [];
        }
        // Get data from Superglobals service instead of direct access
        $this->globals[$name] = service('superglobals')->get_global_array($name);
    }
}