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
use Config\App;
use Config\Curl_Request as ConfigCURLRequest;
use Curl_Share_Handle;
use Sensitive_Parameter;
/**
 * A lightweight HTTP client for sending synchronous HTTP requests via cURL.
 *
 * @see \CodeIgniter\HTTP\CURLRequestTest
 */
class Curl_Request extends Outgoing_Request
{
    /**
     * The response object associated with this request
     *
     * @var ResponseInterface|null
     */
    protected $response;
    /**
     * The original response object associated with this request
     *
     * @var ResponseInterface|null
     */
    protected $response_orig;
    /**
     * The URI associated with this request
     *
     * @var URI
     */
    protected $base_uri;
    /**
     * The setting values
     *
     * @var array
     */
    protected $config;
    /**
     * The default setting values
     *
     * @var array
     */
    protected $default_config = ['timeout' => 0.0, 'connect_timeout' => 150, 'debug' => false, 'verify' => true];
    /**
     * Default values for when 'allow_redirects'
     * option is true.
     *
     * @var array
     */
    protected $redirect_defaults = ['max' => 5, 'strict' => true, 'protocols' => ['http', 'https']];
    /**
     * The number of milliseconds to delay before
     * sending the request.
     *
     * @var float
     */
    protected $delay = 0.0;
    /**
     * The default options from the constructor. Applied to all requests.
     */
    private readonly array $default_options;
    /**
     * Whether share options between requests or not.
     *
     * If true, all the options won't be reset between requests.
     * It may cause an error request with unnecessary headers.
     */
    private readonly bool $share_options;
    /**
     * The share connection instance.
     */
    protected ?Curl_Share_Handle $share_connection = null;
    /**
     * Takes an array of options to set the following possible class properties:
     *
     *  - baseURI
     *  - timeout
     *  - any other request options to use as defaults.
     *
     * @param array<string, mixed> $options
     */
    public function __construct(App $config, URI $uri, ?Response_Interface $response = null, array $options = [])
    {
        if (!function_exists('curl_version')) {
            throw Http_Exception::for_missing_curl();
            // @codeCoverageIgnore
        }
        parent::__construct(Method::GET, $uri);
        $this->response_orig = $response ?? new Response($config);
        // Remove the default Content-Type header.
        $this->response_orig->remove_header('Content-Type');
        $this->base_uri = $uri->use_raw_query_string();
        $this->default_options = $options;
        $this->share_options = config(Config_Curl_Request::class)->share_options ?? true;
        $this->config = $this->default_config;
        $this->parse_options($options);
        // Share Connection
        $opt_share_connection = config(Config_Curl_Request::class)->share_connection_options ?? [CURL_LOCK_DATA_CONNECT, CURL_LOCK_DATA_DNS];
        if ($opt_share_connection !== []) {
            $this->share_connection = curl_share_init();
            foreach (array_unique($opt_share_connection) as $opt) {
                curl_share_setopt($this->share_connection, CURLSHOPT_SHARE, $opt);
            }
        }
    }
    /**
     * Sends an HTTP request to the specified $url. If this is a relative
     * URL, it will be merged with $this->baseURI to form a complete URL.
     *
     * @param string $method HTTP method
     */
    public function request($method, string $url, array $options = []): Response_Interface
    {
        $this->response = clone $this->response_orig;
        $this->parse_options($options);
        $url = $this->prepare_url($url);
        $method = esc(strip_tags($method));
        $this->send($method, $url);
        if ($this->share_options === false) {
            $this->reset_options();
        }
        return $this->response;
    }
    /**
     * Reset all options to default.
     *
     * @return void
     */
    protected function reset_options()
    {
        // Reset headers
        $this->headers = [];
        $this->header_map = [];
        // Reset body
        $this->body = null;
        // Reset configs
        $this->config = $this->default_config;
        // Set the default options for next request
        $this->parse_options($this->default_options);
    }
    /**
     * Convenience method for sending a GET request.
     */
    public function get(string $url, array $options = []): Response_Interface
    {
        return $this->request(Method::GET, $url, $options);
    }
    /**
     * Convenience method for sending a DELETE request.
     */
    public function delete(string $url, array $options = []): Response_Interface
    {
        return $this->request('DELETE', $url, $options);
    }
    /**
     * Convenience method for sending a HEAD request.
     */
    public function head(string $url, array $options = []): Response_Interface
    {
        return $this->request('HEAD', $url, $options);
    }
    /**
     * Convenience method for sending an OPTIONS request.
     */
    public function options(string $url, array $options = []): Response_Interface
    {
        return $this->request('OPTIONS', $url, $options);
    }
    /**
     * Convenience method for sending a PATCH request.
     */
    public function patch(string $url, array $options = []): Response_Interface
    {
        return $this->request('PATCH', $url, $options);
    }
    /**
     * Convenience method for sending a POST request.
     */
    public function post(string $url, array $options = []): Response_Interface
    {
        return $this->request(Method::POST, $url, $options);
    }
    /**
     * Convenience method for sending a PUT request.
     */
    public function put(string $url, array $options = []): Response_Interface
    {
        return $this->request(Method::PUT, $url, $options);
    }
    /**
     * Set the HTTP Authentication.
     *
     * @param string $type basic or digest
     *
     * @return $this
     */
    public function set_auth(
        string $username,
        #[Sensitive_Parameter]
        string $password,
        string $type = 'basic'
    )
    {
        $this->config['auth'] = [$username, $password, $type];
        return $this;
    }
    /**
     * Set form data to be sent.
     *
     * @param bool $multipart Set TRUE if you are sending CURLFiles
     *
     * @return $this
     */
    public function set_form(array $params, bool $multipart = false)
    {
        if ($multipart) {
            $this->config['multipart'] = $params;
        } else {
            $this->config['form_params'] = $params;
        }
        return $this;
    }
    /**
     * Set JSON data to be sent.
     *
     * @param array|bool|float|int|object|string|null $data
     *
     * @return $this
     */
    public function set_json($data)
    {
        $this->config['json'] = $data;
        return $this;
    }
    /**
     * Sets the correct settings based on the options array
     * passed in.
     *
     * @return void
     */
    protected function parse_options(array $options)
    {
        if (array_key_exists('baseURI', $options)) {
            $this->base_uri = $this->base_uri->set_uri($options['baseURI']);
            unset($options['baseURI']);
        }
        if (array_key_exists('headers', $options) && is_array($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                $this->set_header($name, $value);
            }
            unset($options['headers']);
        }
        if (array_key_exists('delay', $options)) {
            // Convert from the milliseconds passed in
            // to the seconds that sleep requires.
            $this->delay = (float) $options['delay'] / 1000;
            unset($options['delay']);
        }
        if (array_key_exists('body', $options)) {
            $this->set_body($options['body']);
            unset($options['body']);
        }
        foreach ($options as $key => $value) {
            $this->config[$key] = $value;
        }
    }
    /**
     * If the $url is a relative URL, will attempt to create
     * a full URL by prepending $this->baseURI to it.
     */
    protected function prepare_url(string $url): string
    {
        // If it's a full URI, then we have nothing to do here...
        if (str_contains($url, '://')) {
            return $url;
        }
        $uri = $this->base_uri->resolve_relative_uri($url);
        // Create the string instead of casting to prevent baseURL muddling
        return URI::create_uri_string($uri->get_scheme(), $uri->get_authority(), $uri->get_path(), $uri->get_query(), $uri->get_fragment());
    }
    /**
     * Fires the actual cURL request.
     *
     * @return ResponseInterface
     */
    public function send(string $method, string $url)
    {
        // Reset our curl options so we're on a fresh slate.
        $curl_options = [];
        if (!empty($this->config['query']) && is_array($this->config['query'])) {
            // This is likely too naive a solution.
            // Should look into handling when $url already
            // has query vars on it.
            $url .= '?' . http_build_query($this->config['query']);
            unset($this->config['query']);
        }
        $curl_options[CURLOPT_URL] = $url;
        $curl_options[CURLOPT_RETURNTRANSFER] = true;
        if ($this->share_connection instanceof Curl_Share_Handle) {
            $curl_options[CURLOPT_SHARE] = $this->share_connection;
        }
        $curl_options[CURLOPT_HEADER] = true;
        // Disable @file uploads in post data.
        $curl_options[CURLOPT_SAFE_UPLOAD] = true;
        $curl_options = $this->set_curl_options($curl_options, $this->config);
        $curl_options = $this->apply_method($method, $curl_options);
        $curl_options = $this->apply_request_headers($curl_options);
        // Do we need to delay this request?
        if ($this->delay > 0) {
            usleep((int) $this->delay * 1000000);
        }
        $output = $this->send_request($curl_options);
        // Set the string we want to break our response from
        $break_string = "\r\n\r\n";
        // Remove all intermediate responses
        $output = $this->remove_intermediate_responses($output, $break_string);
        // Split out our headers and body
        $break = strpos($output, $break_string);
        if ($break !== false) {
            // Our headers
            $headers = explode("\n", substr($output, 0, $break));
            $this->set_response_headers($headers);
            // Our body
            $body = substr($output, $break + 4);
            $this->response->set_body($body);
        } else {
            $this->response->set_body($output);
        }
        return $this->response;
    }
    /**
     * Adds $this->headers to the cURL request.
     */
    protected function apply_request_headers(array $curl_options = []): array
    {
        if (empty($this->headers)) {
            return $curl_options;
        }
        $set = [];
        foreach (array_keys($this->headers) as $name) {
            $set[] = $name . ': ' . $this->get_header_line($name);
        }
        $curl_options[CURLOPT_HTTPHEADER] = $set;
        return $curl_options;
    }
    /**
     * Apply method
     */
    protected function apply_method(string $method, array $curl_options): array
    {
        $this->method = $method;
        $curl_options[CURLOPT_CUSTOMREQUEST] = $method;
        $size = strlen($this->body ?? '');
        // Have content?
        if ($size > 0) {
            return $this->apply_body($curl_options);
        }
        if ($method === Method::PUT || $method === Method::POST) {
            // See http://tools.ietf.org/html/rfc7230#section-3.3.2
            if ($this->header('content-length') === null && !isset($this->config['multipart'])) {
                $this->set_header('Content-Length', '0');
            }
        } elseif ($method === 'HEAD') {
            $curl_options[CURLOPT_NOBODY] = 1;
        }
        return $curl_options;
    }
    /**
     * Apply body
     */
    protected function apply_body(array $curl_options = []): array
    {
        if (!empty($this->body)) {
            $curl_options[CURLOPT_POSTFIELDS] = (string) $this->get_body();
        }
        return $curl_options;
    }
    /**
     * Parses the header retrieved from the cURL response into
     * our Response object.
     *
     * @return void
     */
    protected function set_response_headers(array $headers = [])
    {
        foreach ($headers as $header) {
            if (($pos = strpos($header, ':')) !== false) {
                $title = trim(substr($header, 0, $pos));
                $value = trim(substr($header, $pos + 1));
                if ($this->response instanceof Response) {
                    $this->response->add_header($title, $value);
                } else {
                    $this->response->set_header($title, $value);
                }
            } elseif (str_starts_with($header, 'HTTP')) {
                preg_match('#^HTTP\/([12](?:\.[01])?) (\d+) (.+)#', $header, $matches);
                if (isset($matches[1])) {
                    $this->response->set_protocol_version($matches[1]);
                }
                if (isset($matches[2])) {
                    $this->response->set_status_code((int) $matches[2], $matches[3] ?? null);
                }
            }
        }
    }
    /**
     * Set CURL options
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function set_curl_options(array $curl_options = [], array $config = [])
    {
        // Auth Headers
        if (!empty($config['auth'])) {
            $curl_options[CURLOPT_USERPWD] = $config['auth'][0] . ':' . $config['auth'][1];
            if (!empty($config['auth'][2]) && strtolower($config['auth'][2]) === 'digest') {
                $curl_options[CURLOPT_HTTPAUTH] = CURLAUTH_DIGEST;
            } else {
                $curl_options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            }
        }
        // Certificate
        if (!empty($config['cert'])) {
            $cert = $config['cert'];
            if (is_array($cert)) {
                $curl_options[CURLOPT_SSLCERTPASSWD] = $cert[1];
                $cert = $cert[0];
            }
            if (!is_file($cert)) {
                throw Http_Exception::for_ssl_cert_not_found($cert);
            }
            $curl_options[CURLOPT_SSLCERT] = $cert;
        }
        // SSL Verification
        if (isset($config['verify'])) {
            if (is_string($config['verify'])) {
                $file = realpath($config['verify']) ?: $config['verify'];
                if (!is_file($file)) {
                    throw Http_Exception::for_invalid_ssl_key($config['verify']);
                }
                $curl_options[CURLOPT_CAINFO] = $file;
                $curl_options[CURLOPT_SSL_VERIFYPEER] = true;
                $curl_options[CURLOPT_SSL_VERIFYHOST] = 2;
            } elseif (is_bool($config['verify'])) {
                $curl_options[CURLOPT_SSL_VERIFYPEER] = $config['verify'];
                $curl_options[CURLOPT_SSL_VERIFYHOST] = $config['verify'] ? 2 : 0;
            }
        }
        // Proxy
        if (isset($config['proxy'])) {
            $curl_options[CURLOPT_HTTPPROXYTUNNEL] = true;
            $curl_options[CURLOPT_PROXY] = $config['proxy'];
        }
        // Debug
        if ($config['debug']) {
            $curl_options[CURLOPT_VERBOSE] = 1;
            $curl_options[CURLOPT_STDERR] = is_string($config['debug']) ? fopen($config['debug'], 'a+b') : fopen('php://stderr', 'wb');
        }
        // Decode Content
        if (!empty($config['decode_content'])) {
            $accept = $this->get_header_line('Accept-Encoding');
            if ($accept !== '') {
                $curl_options[CURLOPT_ENCODING] = $accept;
            } else {
                $curl_options[CURLOPT_ENCODING] = '';
                $curl_options[CURLOPT_HTTPHEADER] = 'Accept-Encoding';
            }
        }
        // Allow Redirects
        if (array_key_exists('allow_redirects', $config)) {
            $settings = $this->redirect_defaults;
            if (is_array($config['allow_redirects'])) {
                $settings = array_merge($settings, $config['allow_redirects']);
            }
            if ($config['allow_redirects'] === false) {
                $curl_options[CURLOPT_FOLLOWLOCATION] = 0;
            } else {
                $curl_options[CURLOPT_FOLLOWLOCATION] = 1;
                $curl_options[CURLOPT_MAXREDIRS] = $settings['max'];
                if ($settings['strict'] === true) {
                    $curl_options[CURLOPT_POSTREDIR] = 1 | 2 | 4;
                }
                $protocols = 0;
                foreach ($settings['protocols'] as $proto) {
                    $protocols += constant('CURLPROTO_' . strtoupper($proto));
                }
                $curl_options[CURLOPT_REDIR_PROTOCOLS] = $protocols;
            }
        }
        // DNS Cache Timeout
        if (isset($config['dns_cache_timeout']) && is_numeric($config['dns_cache_timeout']) && $config['dns_cache_timeout'] >= -1) {
            $curl_options[CURLOPT_DNS_CACHE_TIMEOUT] = (int) $config['dns_cache_timeout'];
        }
        // Fresh Connect (default true)
        $curl_options[CURLOPT_FRESH_CONNECT] = isset($config['fresh_connect']) && is_bool($config['fresh_connect']) ? $config['fresh_connect'] : true;
        // Timeout
        $curl_options[CURLOPT_TIMEOUT_MS] = (float) $config['timeout'] * 1000;
        // Connection Timeout
        $curl_options[CURLOPT_CONNECTTIMEOUT_MS] = (float) $config['connect_timeout'] * 1000;
        // Post Data - application/x-www-form-urlencoded
        if (!empty($config['form_params']) && is_array($config['form_params'])) {
            $post_fields = http_build_query($config['form_params']);
            $curl_options[CURLOPT_POSTFIELDS] = $post_fields;
            // Ensure content-length is set, since CURL doesn't seem to
            // calculate it when HTTPHEADER is set.
            $this->set_header('Content-Length', (string) strlen($post_fields));
            $this->set_header('Content-Type', 'application/x-www-form-urlencoded');
        }
        // Post Data - multipart/form-data
        if (!empty($config['multipart']) && is_array($config['multipart'])) {
            // setting the POSTFIELDS option automatically sets multipart
            $curl_options[CURLOPT_POSTFIELDS] = $config['multipart'];
        }
        // HTTP Errors
        $curl_options[CURLOPT_FAILONERROR] = array_key_exists('http_errors', $config) ? (bool) $config['http_errors'] : true;
        // JSON
        if (isset($config['json'])) {
            // Will be set as the body in `applyBody()`
            $json = json_encode($config['json']);
            $this->set_body($json);
            $this->set_header('Content-Type', 'application/json');
            $this->set_header('Content-Length', (string) strlen($json));
        }
        // Resolve IP
        if (array_key_exists('force_ip_resolve', $config)) {
            $curl_options[CURLOPT_IPRESOLVE] = match ($config['force_ip_resolve']) {
                'v4' => CURL_IPRESOLVE_V4,
                'v6' => CURL_IPRESOLVE_V6,
                default => CURL_IPRESOLVE_WHATEVER,
            };
        }
        // version
        if (!empty($config['version'])) {
            $version = sprintf('%.1F', $config['version']);
            if ($version === '1.0') {
                $curl_options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_0;
            } elseif ($version === '1.1') {
                $curl_options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
            } elseif ($version === '2.0') {
                $curl_options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2_0;
            } elseif ($version === '3.0') {
                if (!defined('CURL_HTTP_VERSION_3')) {
                    define('CURL_HTTP_VERSION_3', 30);
                }
                $curl_options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_3;
            }
        }
        // Cookie
        if (isset($config['cookie'])) {
            $curl_options[CURLOPT_COOKIEJAR] = $config['cookie'];
            $curl_options[CURLOPT_COOKIEFILE] = $config['cookie'];
        }
        // User Agent
        if (isset($config['user_agent'])) {
            $curl_options[CURLOPT_USERAGENT] = $config['user_agent'];
        }
        return $curl_options;
    }
    /**
     * Does the actual work of initializing cURL, setting the options,
     * and grabbing the output.
     *
     * @param array<int, mixed> $curlOptions
     *
     * @codeCoverageIgnore
     */
    protected function send_request(array $curl_options = []): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, $curl_options);
        // Send the request and wait for a response.
        $output = curl_exec($ch);
        if ($output === false) {
            throw Http_Exception::for_curl_error((string) curl_errno($ch), curl_error($ch));
        }
        return $output;
    }
    private function remove_intermediate_responses(string $output, string $break_string): string
    {
        while (true) {
            // Check if we should remove the current response
            if ($this->should_remove_current_response($output, $break_string)) {
                $break_string_pos = strpos($output, $break_string);
                if ($break_string_pos !== false) {
                    $output = substr($output, $break_string_pos + 4);
                    continue;
                }
            }
            // No more intermediate responses to remove
            break;
        }
        return $output;
    }
    /**
     * Check if the current response (at the beginning of output) should be removed.
     */
    private function should_remove_current_response(string $output, string $break_string): bool
    {
        // HTTP/x.x 1xx responses (Continue, Processing, etc.)
        if (preg_match('/^HTTP\/\d+(?:\.\d+)?\s+1\d\d\s/', $output)) {
            return true;
        }
        // HTTP/x.x 200 Connection established (proxy responses)
        if (preg_match('/^HTTP\/\d+(?:\.\d+)?\s+200\s+Connection\s+established/i', $output)) {
            return true;
        }
        // HTTP/x.x 3xx responses (redirects) - only if redirects are allowed
        $allow_redirects = isset($this->config['allow_redirects']) && $this->config['allow_redirects'] !== false;
        if ($allow_redirects && preg_match('/^HTTP\/\d+(?:\.\d+)?\s+3\d\d\s/', $output)) {
            // Check if there's a Location header
            $break_string_pos = strpos($output, $break_string);
            if ($break_string_pos !== false) {
                $header_section = substr($output, 0, $break_string_pos);
                $headers = explode("\n", $header_section);
                foreach ($headers as $header) {
                    if (str_starts_with(strtolower($header), 'location:')) {
                        return true;
                        // Found location header, this is a redirect to remove
                    }
                }
            }
        }
        // Digest auth challenges - only remove if there's another response after
        if (isset($this->config['auth'][2]) && $this->config['auth'][2] === 'digest') {
            $break_string_pos = strpos($output, $break_string);
            if ($break_string_pos !== false) {
                $header_section = substr($output, 0, $break_string_pos);
                if (str_contains($header_section, 'WWW-Authenticate: Digest')) {
                    $next_break_pos = strpos($output, $break_string, $break_string_pos + 4);
                    return $next_break_pos !== false;
                    // Only remove if there's another response
                }
            }
        }
        return false;
    }
}