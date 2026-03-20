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

/**
 * Representation of an outgoing, client-side request.
 *
 * @see \CodeIgniter\HTTP\OutgoingRequestTest
 */
class Outgoing_Request extends Message implements Outgoing_Request_Interface
{
    /**
     * Request method.
     *
     * @var string
     */
    protected $method;
    /**
     * A URI instance.
     *
     * @var URI|null
     */
    protected $uri;
    /**
     * @param string      $method HTTP method
     * @param string|null $body
     */
    public function __construct(string $method, ?URI $uri = null, array $headers = [], $body = null, string $version = '1.1')
    {
        $this->method = $method;
        $this->uri = $uri;
        foreach ($headers as $header => $value) {
            $this->set_header($header, $value);
        }
        $this->body = $body;
        $this->protocol_version = $version;
        if (!$this->has_header('Host') && $this->uri->get_host() !== '') {
            $this->set_header('Host', $this->get_host_from_uri($this->uri));
        }
    }
    private function get_host_from_uri(URI $uri): string
    {
        $host = $uri->get_host();
        return $host . ($uri->get_port() > 0 ? ':' . $uri->get_port() : '');
    }
    /**
     * Retrieves the HTTP method of the request.
     *
     * @return string Returns the request method (always uppercase)
     */
    public function get_method(): string
    {
        return $this->method;
    }
    /**
     * Sets the request method. Used when spoofing the request.
     *
     * @return $this
     *
     * @deprecated Use withMethod() instead for immutability
     */
    public function set_method(string $method)
    {
        $this->method = $method;
        return $this;
    }
    /**
     * Returns an instance with the specified method.
     *
     * @param string $method
     *
     * @return static
     */
    public function with_method($method)
    {
        $request = clone $this;
        $request->method = $method;
        return $request;
    }
    /**
     * Retrieves the URI instance.
     *
     * @return URI|null
     */
    public function get_uri()
    {
        return $this->uri;
    }
    /**
     * Returns an instance with the provided URI.
     *
     * @param URI  $uri          New request URI to use.
     * @param bool $preserveHost Preserve the original state of the Host header.
     *
     * @return static
     */
    public function with_uri(URI $uri, $preserve_host = false)
    {
        $request = clone $this;
        $request->uri = $uri;
        if ($preserve_host) {
            if ($this->is_host_header_missing_or_empty() && $uri->get_host() !== '') {
                $request->set_header('Host', $this->get_host_from_uri($uri));
                return $request;
            }
            if ($this->is_host_header_missing_or_empty() && $uri->get_host() === '') {
                return $request;
            }
            if (!$this->is_host_header_missing_or_empty()) {
                return $request;
            }
        }
        if ($uri->get_host() !== '') {
            $request->set_header('Host', $this->get_host_from_uri($uri));
        }
        return $request;
    }
    private function is_host_header_missing_or_empty(): bool
    {
        if (!$this->has_header('Host')) {
            return true;
        }
        return $this->header('Host')->get_value() === '';
    }
}