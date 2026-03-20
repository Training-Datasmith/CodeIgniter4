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

use Code_Igniter\Cookie\Cookie;
use Code_Igniter\Cookie\Cookie_Store;
use Code_Igniter\Cookie\Exceptions\Cookie_Exception;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\HTTP\Exceptions\Http_Exception;
use Code_Igniter\I18n\Time;
use Code_Igniter\Pager\Pager_Interface;
use Code_Igniter\Security\Exceptions\Security_Exception;
use Config\Cookie as CookieConfig;
use DateTime;
use DateTimeZone;
/**
 * Response Trait
 *
 * Additional methods to make a PSR-7 Response class
 * compliant with the framework's own ResponseInterface.
 *
 * @see https://github.com/php-fig/http-message/blob/master/src/ResponseInterface.php
 */
trait Response_Trait
{
    /**
     * Content security policy handler
     *
     * @var ContentSecurityPolicy
     */
    protected $CSP;
    /**
     * CookieStore instance.
     *
     * @var CookieStore
     */
    protected $cookie_store;
    /**
     * Type of format the body is in.
     * Valid: html, json, xml
     *
     * @var string
     */
    protected $body_format = 'html';
    /**
     * Return an instance with the specified status code and, optionally, reason phrase.
     *
     * If no reason phrase is specified, will default recommended reason phrase for
     * the response's status code.
     *
     * @see http://tools.ietf.org/html/rfc7231#section-6
     * @see http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     *
     * @param int    $code   The 3-digit integer result code to set.
     * @param string $reason The reason phrase to use with the
     *                       provided status code; if none is provided, will
     *                       default to the IANA name.
     *
     * @return $this
     *
     * @throws HTTPException For invalid status code arguments.
     */
    public function set_status_code(int $code, string $reason = '')
    {
        // Valid range?
        if ($code < 100 || $code > 599) {
            throw Http_Exception::for_invalid_status_code($code);
        }
        // Unknown and no message?
        if (!array_key_exists($code, static::$status_codes) && $reason === '') {
            throw Http_Exception::for_unkown_status_code($code);
        }
        $this->status_code = $code;
        $this->reason = $reason !== '' ? $reason : static::$status_codes[$code];
        return $this;
    }
    // --------------------------------------------------------------------
    // Convenience Methods
    // --------------------------------------------------------------------
    /**
     * Sets the date header
     *
     * @return $this
     */
    public function set_date(DateTime $date)
    {
        $date->set_timezone(new DateTimeZone('UTC'));
        $this->set_header('Date', $date->format('D, d M Y H:i:s') . ' GMT');
        return $this;
    }
    /**
     * Set the Link Header
     *
     * @see http://tools.ietf.org/html/rfc5988
     *
     * @return $this
     *
     * @todo Recommend moving to Pager
     */
    public function set_link(Pager_Interface $pager)
    {
        $links = '';
        $previous = $pager->get_previous_page_uri();
        if (is_string($previous) && $previous !== '') {
            $links .= '<' . $pager->get_page_uri($pager->get_first_page()) . '>; rel="first",';
            $links .= '<' . $previous . '>; rel="prev"';
        }
        $next = $pager->get_next_page_uri();
        if (is_string($next) && $next !== '' && is_string($previous) && $previous !== '') {
            $links .= ',';
        }
        if (is_string($next) && $next !== '') {
            $links .= '<' . $next . '>; rel="next",';
            $links .= '<' . $pager->get_page_uri($pager->get_last_page()) . '>; rel="last"';
        }
        $this->set_header('Link', $links);
        return $this;
    }
    /**
     * Sets the Content Type header for this response with the mime type
     * and, optionally, the charset.
     *
     * @return $this
     */
    public function set_content_type(string $mime, string $charset = 'UTF-8')
    {
        // add charset attribute if not already there and provided as parm
        if (strpos($mime, 'charset=') < 1 && $charset !== '') {
            $mime .= '; charset=' . $charset;
        }
        $this->remove_header('Content-Type');
        // replace existing content type
        $this->set_header('Content-Type', $mime);
        return $this;
    }
    /**
     * Converts the $body into JSON and sets the Content Type header.
     *
     * @param array|object|string $body
     *
     * @return $this
     */
    public function set_json($body, bool $unencoded = false)
    {
        $this->body = $this->format_body($body, 'json' . ($unencoded ? '-unencoded' : ''));
        return $this;
    }
    /**
     * Returns the current body, converted to JSON is it isn't already.
     *
     * @return string|null
     *
     * @throws InvalidArgumentException If the body property is not array.
     */
    public function get_json()
    {
        $body = $this->body;
        if ($this->body_format !== 'json') {
            $body = service('format')->get_formatter('application/json')->format($body);
        }
        return $body ?: null;
    }
    /**
     * Converts $body into XML, and sets the correct Content-Type.
     *
     * @param array|string $body
     *
     * @return $this
     */
    public function set_xml($body)
    {
        $this->body = $this->format_body($body, 'xml');
        return $this;
    }
    /**
     * Retrieves the current body into XML and returns it.
     *
     * @return bool|string|null
     *
     * @throws InvalidArgumentException If the body property is not array.
     */
    public function get_xml()
    {
        $body = $this->body;
        if ($this->body_format !== 'xml') {
            $body = service('format')->get_formatter('application/xml')->format($body);
        }
        return $body;
    }
    /**
     * Handles conversion of the data into the appropriate format,
     * and sets the correct Content-Type header for our response.
     *
     * @param array|object|string $body
     * @param string              $format Valid: json, xml
     *
     * @return false|string
     *
     * @throws InvalidArgumentException If the body property is not string or array.
     */
    protected function format_body($body, string $format)
    {
        $this->body_format = $format === 'json-unencoded' ? 'json' : $format;
        $mime = "application/{$this->body_format}";
        $this->set_content_type($mime);
        // Nothing much to do for a string...
        if (!is_string($body) || $format === 'json-unencoded') {
            $body = service('format')->get_formatter($mime)->format($body);
        }
        return $body;
    }
    // --------------------------------------------------------------------
    // Cache Control Methods
    //
    // http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9
    // --------------------------------------------------------------------
    /**
     * Sets the appropriate headers to ensure this response
     * is not cached by the browsers.
     *
     * @return $this
     *
     * @todo Recommend researching these directives, might need: 'private', 'no-transform', 'no-store', 'must-revalidate'
     *
     * @see DownloadResponse::noCache()
     */
    public function no_cache()
    {
        $this->remove_header('Cache-Control');
        $this->set_header('Cache-Control', ['no-store', 'max-age=0', 'no-cache']);
        return $this;
    }
    /**
     * A shortcut method that allows the developer to set all of the
     * cache-control headers in one method call.
     *
     * The options array is used to provide the cache-control directives
     * for the header. It might look something like:
     *
     *      $options = [
     *          'max-age'  => 300,
     *          's-maxage' => 900
     *          'etag'     => 'abcde',
     *      ];
     *
     * Typical options are:
     *  - etag
     *  - last-modified
     *  - max-age
     *  - s-maxage
     *  - private
     *  - public
     *  - must-revalidate
     *  - proxy-revalidate
     *  - no-transform
     *
     * @return $this
     */
    public function set_cache(array $options = [])
    {
        if ($options === []) {
            return $this;
        }
        $this->remove_header('Cache-Control');
        $this->remove_header('ETag');
        // ETag
        if (isset($options['etag'])) {
            $this->set_header('ETag', $options['etag']);
            unset($options['etag']);
        }
        // Last Modified
        if (isset($options['last-modified'])) {
            $this->set_last_modified($options['last-modified']);
            unset($options['last-modified']);
        }
        $this->set_header('Cache-Control', $options);
        return $this;
    }
    /**
     * Sets the Last-Modified date header.
     *
     * $date can be either a string representation of the date or,
     * preferably, an instance of DateTime.
     *
     * @param DateTime|string $date
     *
     * @return $this
     */
    public function set_last_modified($date)
    {
        if ($date instanceof DateTime) {
            $date->set_timezone(new DateTimeZone('UTC'));
            $this->set_header('Last-Modified', $date->format('D, d M Y H:i:s') . ' GMT');
        } elseif (is_string($date)) {
            $this->set_header('Last-Modified', $date);
        }
        return $this;
    }
    // --------------------------------------------------------------------
    // Output Methods
    // --------------------------------------------------------------------
    /**
     * Sends the output to the browser.
     *
     * @return $this
     */
    public function send()
    {
        // If we're enforcing a Content Security Policy,
        // we need to give it a chance to build out it's headers.
        $this->CSP->finalize($this);
        $this->send_headers();
        $this->send_cookies();
        $this->send_body();
        return $this;
    }
    /**
     * Sends the headers of this HTTP response to the browser.
     *
     * @return $this
     */
    public function send_headers()
    {
        // Have the headers already been sent?
        if ($this->pretend || headers_sent()) {
            return $this;
        }
        // Per spec, MUST be sent with each request, if possible.
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec13.html
        if (!isset($this->headers['Date']) && PHP_SAPI !== 'cli-server') {
            $this->set_date(DateTime::create_from_format('U', (string) Time::now()->get_timestamp()));
        }
        // HTTP Status
        header(sprintf('HTTP/%s %s %s', $this->get_protocol_version(), $this->get_status_code(), $this->get_reason_phrase()), true, $this->get_status_code());
        // Send all of our headers
        foreach ($this->headers() as $name => $value) {
            if ($value instanceof Header) {
                header($name . ': ' . $value->get_value_line(), true, $this->get_status_code());
            } else {
                $replace = true;
                foreach ($value as $header) {
                    header($name . ': ' . $header->get_value_line(), $replace, $this->get_status_code());
                    $replace = false;
                }
            }
        }
        return $this;
    }
    /**
     * Sends the Body of the message to the browser.
     *
     * @return $this
     */
    public function send_body()
    {
        echo $this->body;
        return $this;
    }
    /**
     * Perform a redirect to a new URL, in two flavors: header or location.
     *
     * @param string   $uri  The URI to redirect to
     * @param int|null $code The type of redirection, defaults to 302
     *
     * @return $this
     *
     * @throws HTTPException For invalid status code.
     */
    public function redirect(string $uri, string $method = 'auto', ?int $code = null)
    {
        // IIS environment likely? Use 'refresh' for better compatibility
        $superglobals = service('superglobals');
        $server_software = $superglobals->server('SERVER_SOFTWARE');
        if ($method === 'auto' && $server_software !== null && str_contains($server_software, 'Microsoft-IIS')) {
            $method = 'refresh';
        } elseif ($method !== 'refresh' && $code === null) {
            // override status code for HTTP/1.1 & higher
            $server_protocol = $superglobals->server('SERVER_PROTOCOL');
            $request_method = $superglobals->server('REQUEST_METHOD');
            if ($server_protocol !== null && $request_method !== null && $this->get_protocol_version() >= 1.1) {
                if ($request_method === Method::GET) {
                    $code = 302;
                } elseif (in_array($request_method, [Method::POST, Method::PUT, Method::DELETE], true)) {
                    // reference: https://en.wikipedia.org/wiki/Post/Redirect/Get
                    $code = 303;
                } else {
                    $code = 307;
                }
            }
        }
        if ($code === null) {
            $code = 302;
        }
        match ($method) {
            'refresh' => $this->set_header('Refresh', '0;url=' . $uri),
            default => $this->set_header('Location', $uri),
        };
        $this->set_status_code($code);
        return $this;
    }
    /**
     * Set a cookie
     *
     * Accepts an arbitrary number of binds (up to 7) or an associative
     * array in the first parameter containing all the values.
     *
     * @param array|Cookie|string $name     Cookie name / array containing binds / Cookie object
     * @param string              $value    Cookie value
     * @param int                 $expire   Cookie expiration time in seconds
     * @param string              $domain   Cookie domain (e.g.: '.yourdomain.com')
     * @param string              $path     Cookie path (default: '/')
     * @param string              $prefix   Cookie name prefix ('': the default prefix)
     * @param bool|null           $secure   Whether to only transfer cookies via SSL
     * @param bool|null           $httponly Whether only make the cookie accessible via HTTP (no javascript)
     * @param string|null         $samesite
     *
     * @return $this
     */
    public function set_cookie($name, $value = '', $expire = 0, $domain = '', $path = '/', $prefix = '', $secure = null, $httponly = null, $samesite = null)
    {
        if ($name instanceof Cookie) {
            $this->cookie_store = $this->cookie_store->put($name);
            return $this;
        }
        $cookie_config = config(Cookie_Config::class);
        $secure ??= $cookie_config->secure;
        $httponly ??= $cookie_config->httponly;
        $samesite ??= $cookie_config->samesite;
        if (is_array($name)) {
            // always leave 'name' in last place, as the loop will break otherwise, due to ${$item}
            foreach (['samesite', 'value', 'expire', 'domain', 'path', 'prefix', 'secure', 'httponly', 'name'] as $item) {
                if (isset($name[$item])) {
                    ${$item} = $name[$item];
                }
            }
        }
        if (is_numeric($expire)) {
            $expire = $expire > 0 ? Time::now()->get_timestamp() + $expire : 0;
        }
        $cookie = new Cookie($name, $value, ['expires' => $expire ?: 0, 'domain' => $domain, 'path' => $path, 'prefix' => $prefix, 'secure' => $secure, 'httponly' => $httponly, 'samesite' => $samesite ?? '']);
        $this->cookie_store = $this->cookie_store->put($cookie);
        return $this;
    }
    /**
     * Returns the `CookieStore` instance.
     *
     * @return CookieStore
     */
    public function get_cookie_store()
    {
        return $this->cookie_store;
    }
    /**
     * Checks to see if the Response has a specified cookie or not.
     */
    public function has_cookie(string $name, ?string $value = null, string $prefix = ''): bool
    {
        $prefix = $prefix !== '' ? $prefix : Cookie::set_defaults()['prefix'];
        // to retain BC
        return $this->cookie_store->has($name, $prefix, $value);
    }
    /**
     * Returns the cookie
     *
     * @param string $prefix Cookie prefix.
     *                       '': the default prefix
     *
     * @return array<string, Cookie>|Cookie|null
     */
    public function get_cookie(?string $name = null, string $prefix = '')
    {
        if ((string) $name === '') {
            return $this->cookie_store->display();
        }
        try {
            $prefix = $prefix !== '' ? $prefix : Cookie::set_defaults()['prefix'];
            // to retain BC
            return $this->cookie_store->get($name, $prefix);
        } catch (Cookie_Exception $e) {
            log_message('error', (string) $e);
            return null;
        }
    }
    /**
     * Sets a cookie to be deleted when the response is sent.
     *
     * @return $this
     */
    public function delete_cookie(string $name = '', string $domain = '', string $path = '/', string $prefix = '')
    {
        if ($name === '') {
            return $this;
        }
        $prefix = $prefix !== '' ? $prefix : Cookie::set_defaults()['prefix'];
        // to retain BC
        $prefixed = $prefix . $name;
        $store = $this->cookie_store;
        $found = false;
        /** @var Cookie $cookie */
        foreach ($store as $cookie) {
            if ($cookie->get_prefixed_name() === $prefixed) {
                if ($domain !== $cookie->get_domain()) {
                    continue;
                }
                if ($path !== $cookie->get_path()) {
                    continue;
                }
                $cookie = $cookie->with_value('')->with_expired();
                $found = true;
                $this->cookie_store = $store->put($cookie);
                break;
            }
        }
        if (!$found) {
            $this->set_cookie($name, '', 0, $domain, $path, $prefix);
        }
        return $this;
    }
    /**
     * Returns all cookies currently set.
     *
     * @return array<string, Cookie>
     */
    public function get_cookies()
    {
        return $this->cookie_store->display();
    }
    /**
     * Actually sets the cookies.
     *
     * @return void
     */
    protected function send_cookies()
    {
        if ($this->pretend) {
            return;
        }
        $this->dispatch_cookies();
    }
    private function dispatch_cookies(): void
    {
        /** @var IncomingRequest $request */
        $request = service('request');
        foreach ($this->cookie_store->display() as $cookie) {
            if ($cookie->is_secure() && !$request->is_secure()) {
                throw Security_Exception::for_insecure_cookie();
            }
            $name = $cookie->get_prefixed_name();
            $value = $cookie->get_value();
            $options = $cookie->get_options();
            if ($cookie->is_raw()) {
                $this->do_set_raw_cookie($name, $value, $options);
            } else {
                $this->do_set_cookie($name, $value, $options);
            }
        }
        $this->cookie_store->clear();
    }
    /**
     * Extracted call to `setrawcookie()` in order to run unit tests on it.
     *
     * @codeCoverageIgnore
     */
    private function do_set_raw_cookie(string $name, string $value, array $options): void
    {
        setrawcookie($name, $value, $options);
    }
    /**
     * Extracted call to `setcookie()` in order to run unit tests on it.
     *
     * @codeCoverageIgnore
     */
    private function do_set_cookie(string $name, string $value, array $options): void
    {
        setcookie($name, $value, $options);
    }
    /**
     * Force a download.
     *
     * Generates the headers that force a download to happen. And
     * sends the file to the browser.
     *
     * @param string      $filename The name you want the downloaded file to be named
     *                              or the path to the file to send
     * @param string|null $data     The data to be downloaded. Set null if the $filename is the file path
     * @param bool        $setMime  Whether to try and send the actual MIME type
     *
     * @return DownloadResponse|null
     */
    public function download(string $filename = '', $data = '', bool $set_mime = false)
    {
        if ($filename === '' || $data === '') {
            return null;
        }
        $filepath = '';
        if ($data === null) {
            $filepath = $filename;
            $filename = explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $filename));
            $filename = end($filename);
        }
        $response = new Download_Response($filename, $set_mime);
        if ($filepath !== '') {
            $response->set_file_path($filepath);
        } elseif ($data !== null) {
            $response->set_binary($data);
        }
        return $response;
    }
    public function get_csp(): Content_Security_Policy
    {
        return $this->CSP;
    }
}