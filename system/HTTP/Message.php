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
/**
 * An HTTP message
 *
 * @see \CodeIgniter\HTTP\MessageTest
 */
class Message implements Message_Interface
{
    use Message_Trait;
    /**
     * Protocol version
     *
     * @var string
     */
    protected $protocol_version;
    /**
     * List of valid protocol versions
     *
     * @var array
     */
    protected $valid_protocol_versions = ['1.0', '1.1', '2.0', '3.0'];
    /**
     * Message body
     *
     * @var string|null
     */
    protected $body;
    /**
     * Returns the Message's body.
     *
     * @return string|null
     */
    public function get_body()
    {
        return $this->body;
    }
    /**
     * Returns an array containing all headers.
     *
     * @return array<string, Header> An array of the request headers
     *
     * @deprecated Use Message::headers() to make room for PSR-7
     *
     * @TODO Incompatible return value with PSR-7
     *
     * @codeCoverageIgnore
     */
    public function get_headers(): array
    {
        return $this->headers();
    }
    /**
     * Returns a single header object. If multiple headers with the same
     * name exist, then will return an array of header objects.
     *
     * @return array|Header|null
     *
     * @deprecated Use Message::header() to make room for PSR-7
     *
     * @TODO Incompatible return value with PSR-7
     *
     * @codeCoverageIgnore
     */
    public function get_header(string $name)
    {
        return $this->header($name);
    }
    /**
     * Determines whether a header exists.
     */
    public function has_header(string $name): bool
    {
        $orig_name = $this->get_header_name($name);
        return isset($this->headers[$orig_name]);
    }
    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     */
    public function get_header_line(string $name): string
    {
        if ($this->has_multiple_headers($name)) {
            throw new InvalidArgumentException('The header "' . $name . '" already has multiple headers.' . ' You cannot use getHeaderLine().');
        }
        $orig_name = $this->get_header_name($name);
        if (!array_key_exists($orig_name, $this->headers)) {
            return '';
        }
        return $this->headers[$orig_name]->get_value_line();
    }
    /**
     * Returns the HTTP Protocol Version.
     */
    public function get_protocol_version(): string
    {
        return $this->protocol_version ?? '1.1';
    }
}