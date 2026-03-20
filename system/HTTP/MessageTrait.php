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

use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\HTTP\Exceptions\Http_Exception;
/**
 * Message Trait
 * Additional methods to make a PSR-7 Message class
 * compliant with the framework's own MessageInterface.
 *
 * @see https://github.com/php-fig/http-message/blob/master/src/MessageInterface.php
 */
trait Message_Trait
{
    /**
     * List of all HTTP request headers.
     *
     * [name => Header]
     * or
     * [name => [Header1, Header2]]
     *
     * @var array<string, Header|list<Header>>
     */
    protected $headers = [];
    /**
     * Holds a map of lower-case header names
     * and their normal-case key as it is in $headers.
     * Used for case-insensitive header access.
     *
     * @var array
     */
    protected $header_map = [];
    // --------------------------------------------------------------------
    // Body
    // --------------------------------------------------------------------
    /**
     * Sets the body of the current message.
     *
     * @param string $data
     *
     * @return $this
     */
    public function set_body($data): self
    {
        $this->body = $data;
        return $this;
    }
    /**
     * Appends data to the body of the current message.
     *
     * @param string $data
     *
     * @return $this
     */
    public function append_body($data): self
    {
        $this->body .= (string) $data;
        return $this;
    }
    // --------------------------------------------------------------------
    // Headers
    // --------------------------------------------------------------------
    /**
     * Populates the $headers array with any headers the server knows about.
     */
    public function populate_headers(): void
    {
        $content_type = service('superglobals')->server('CONTENT_TYPE', (string) getenv('CONTENT_TYPE'));
        if (!empty($content_type)) {
            $this->set_header('Content-Type', $content_type);
        }
        unset($content_type);
        $server_array = service('superglobals')->get_server_array();
        foreach (array_keys($server_array) as $key) {
            if (sscanf($key, 'HTTP_%s', $header) === 1) {
                // take SOME_HEADER and turn it into Some-Header
                $header = str_replace('_', ' ', strtolower($header));
                $header = str_replace(' ', '-', ucwords($header));
                $this->set_header($header, $server_array[$key]);
                // Add us to the header map, so we can find them case-insensitively
                $this->header_map[strtolower($header)] = $header;
            }
        }
    }
    /**
     * Returns an array containing all Headers.
     *
     * @return array<string, Header|list<Header>> An array of the Header objects
     */
    public function headers(): array
    {
        // If no headers are defined, but the user is
        // requesting it, then it's likely they want
        // it to be populated so do that...
        if (empty($this->headers)) {
            $this->populate_headers();
        }
        return $this->headers;
    }
    /**
     * Returns a single Header object. If multiple headers with the same
     * name exist, then will return an array of header objects.
     *
     * @param string $name
     *
     * @return Header|list<Header>|null
     */
    public function header($name)
    {
        $orig_name = $this->get_header_name($name);
        return $this->headers[$orig_name] ?? null;
    }
    /**
     * Sets a header and it's value.
     *
     * @param array|string|null $value
     *
     * @return $this
     */
    public function set_header(string $name, $value): self
    {
        $this->check_multiple_headers($name);
        $orig_name = $this->get_header_name($name);
        if (isset($this->headers[$orig_name]) && is_array($this->headers[$orig_name]->get_value())) {
            if (!is_array($value)) {
                $value = [$value];
            }
            foreach ($value as $v) {
                $this->append_header($orig_name, $v);
            }
        } else {
            $this->headers[$orig_name] = new Header($orig_name, $value);
            $this->header_map[strtolower($orig_name)] = $orig_name;
        }
        return $this;
    }
    private function has_multiple_headers(string $name): bool
    {
        $orig_name = $this->get_header_name($name);
        return isset($this->headers[$orig_name]) && is_array($this->headers[$orig_name]);
    }
    private function check_multiple_headers(string $name): void
    {
        if ($this->has_multiple_headers($name)) {
            throw new InvalidArgumentException('The header "' . $name . '" already has multiple headers.' . ' You cannot change them. If you really need to change, remove the header first.');
        }
    }
    /**
     * Removes a header from the list of headers we track.
     *
     * @return $this
     */
    public function remove_header(string $name): self
    {
        $orig_name = $this->get_header_name($name);
        unset($this->headers[$orig_name], $this->header_map[strtolower($name)]);
        return $this;
    }
    /**
     * Adds an additional header value to any headers that accept
     * multiple values (i.e. are an array or implement ArrayAccess)
     *
     * @return $this
     */
    public function append_header(string $name, ?string $value): self
    {
        $this->check_multiple_headers($name);
        $orig_name = $this->get_header_name($name);
        array_key_exists($orig_name, $this->headers) ? $this->headers[$orig_name]->append_value($value) : $this->set_header($name, $value);
        return $this;
    }
    /**
     * Adds a header (not a header value) with the same name.
     * Use this only when you set multiple headers with the same name,
     * typically, for `Set-Cookie`.
     *
     * @return $this
     */
    public function add_header(string $name, string $value): static
    {
        $orig_name = $this->get_header_name($name);
        if (!isset($this->headers[$orig_name])) {
            $this->set_header($name, $value);
            return $this;
        }
        if (!$this->has_multiple_headers($name) && isset($this->headers[$orig_name])) {
            $this->headers[$orig_name] = [$this->headers[$orig_name]];
        }
        // Add the header.
        $this->headers[$orig_name][] = new Header($orig_name, $value);
        return $this;
    }
    /**
     * Adds an additional header value to any headers that accept
     * multiple values (i.e. are an array or implement ArrayAccess)
     *
     * @return $this
     */
    public function prepend_header(string $name, string $value): self
    {
        $this->check_multiple_headers($name);
        $orig_name = $this->get_header_name($name);
        $this->headers[$orig_name]->prepend_value($value);
        return $this;
    }
    /**
     * Takes a header name in any case, and returns the
     * normal-case version of the header.
     */
    protected function get_header_name(string $name): string
    {
        return $this->header_map[strtolower($name)] ?? $name;
    }
    /**
     * Sets the HTTP protocol version.
     *
     * @return $this
     *
     * @throws HTTPException For invalid protocols
     */
    public function set_protocol_version(string $version): self
    {
        if (!is_numeric($version)) {
            $version = substr($version, strpos($version, '/') + 1);
        }
        // Make sure that version is in the correct format
        $version = number_format((float) $version, 1);
        if (!in_array($version, $this->valid_protocol_versions, true)) {
            throw Http_Exception::for_invalid_http_protocol($version);
        }
        $this->protocol_version = $version;
        return $this;
    }
}