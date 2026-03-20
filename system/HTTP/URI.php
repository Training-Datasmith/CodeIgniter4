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

use Code_Igniter\Exceptions\BadMethodCallException;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\HTTP\Exceptions\Http_Exception;
use Config\App;
use Sensitive_Parameter;
use Stringable;
/**
 * Abstraction for a uniform resource identifier (URI).
 *
 * @see \CodeIgniter\HTTP\URITest
 */
class URI implements Stringable
{
    /**
     * Sub-delimiters used in query strings and fragments.
     */
    public const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';
    /**
     * Unreserved characters used in paths, query strings, and fragments.
     */
    public const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';
    /**
     * Current URI string
     *
     * @var string
     *
     * @deprecated 4.4.0 Not used.
     */
    protected $uri_string;
    /**
     * The Current baseURL.
     *
     * @deprecated 4.4.0 Use SiteURI instead.
     */
    private ?string $base_url = null;
    /**
     * List of URI segments.
     *
     * Starts at 1 instead of 0
     *
     * @var array<int, string>
     */
    protected $segments = [];
    /**
     * The URI Scheme.
     *
     * @var string
     */
    protected $scheme = 'http';
    /**
     * URI User Info
     *
     * @var string|null
     */
    protected $user;
    /**
     * URI User Password
     *
     * @var string|null
     */
    protected $password;
    /**
     * URI Host
     *
     * @var string|null
     */
    protected $host;
    /**
     * URI Port
     *
     * @var int|null
     */
    protected $port;
    /**
     * URI path.
     *
     * @var string|null
     */
    protected $path;
    /**
     * The name of any fragment.
     *
     * @var string
     */
    protected $fragment = '';
    /**
     * The query string.
     *
     * @var array<string, string>
     */
    protected $query = [];
    /**
     * Default schemes/ports.
     *
     * @var array{
     *  http: int,
     *  https: int,
     *  ftp: int,
     *  sftp: int,
     * }
     */
    protected $default_ports = ['http' => 80, 'https' => 443, 'ftp' => 21, 'sftp' => 22];
    /**
     * Whether passwords should be shown in userInfo/authority calls.
     * Default to false because URIs often show up in logs
     *
     * @var bool
     */
    protected $show_password = false;
    /**
     * If true, will continue instead of throwing exceptions.
     *
     * @var bool
     */
    protected $silent = false;
    /**
     * If true, will use raw query string.
     *
     * @var bool
     */
    protected $raw_query_string = false;
    /**
     * Builds a representation of the string from the component parts.
     *
     * @param string|null $scheme URI scheme. E.g., http, ftp
     *
     * @return string URI string with only passed parts. Maybe incomplete as a URI.
     */
    public static function create_uri_string(?string $scheme = null, ?string $authority = null, ?string $path = null, ?string $query = null, ?string $fragment = null): string
    {
        $uri = '';
        if ((string) $scheme !== '') {
            $uri .= $scheme . '://';
        }
        if ((string) $authority !== '') {
            $uri .= $authority;
        }
        if ((string) $path !== '') {
            $uri .= str_ends_with($uri, '/') ? ltrim($path, '/') : '/' . ltrim($path, '/');
        }
        if ((string) $query !== '') {
            $uri .= '?' . $query;
        }
        if ((string) $fragment !== '') {
            $uri .= '#' . $fragment;
        }
        return $uri;
    }
    /**
     * Used when resolving and merging paths to correctly interpret and
     * remove single and double dot segments from the path per
     * RFC 3986 Section 5.2.4
     *
     * @see http://tools.ietf.org/html/rfc3986#section-5.2.4
     *
     * @internal
     */
    public static function remove_dot_segments(string $path): string
    {
        if ($path === '' || $path === '/') {
            return $path;
        }
        $output = [];
        $input = explode('/', $path);
        if ($input[0] === '') {
            unset($input[0]);
            $input = array_values($input);
        }
        // This is not a perfect representation of the
        // RFC, but matches most cases and is pretty
        // much what Guzzle uses. Should be good enough
        // for almost every real use case.
        foreach ($input as $segment) {
            if ($segment === '..') {
                array_pop($output);
            } elseif ($segment !== '.' && $segment !== '') {
                $output[] = $segment;
            }
        }
        $output = implode('/', $output);
        $output = trim($output, '/ ');
        // Add leading slash if necessary
        if (str_starts_with($path, '/')) {
            $output = '/' . $output;
        }
        // Add trailing slash if necessary
        if ($output !== '/' && str_ends_with($path, '/')) {
            $output .= '/';
        }
        return $output;
    }
    /**
     * Constructor.
     *
     * @param string|null $uri The URI to parse.
     *
     * @throws HTTPException
     *
     * @TODO null for param $uri should be removed.
     *      See https://www.php-fig.org/psr/psr-17/#26-urifactoryinterface
     */
    public function __construct(?string $uri = null)
    {
        $this->set_uri($uri);
    }
    /**
     * If $silent == true, then will not throw exceptions and will
     * attempt to continue gracefully.
     *
     * @deprecated 4.4.0 Method not in PSR-7
     *
     * @return URI
     */
    public function set_silent(bool $silent = true)
    {
        $this->silent = $silent;
        return $this;
    }
    /**
     * If $raw == true, then will use parseStr() method
     * instead of native parse_str() function.
     *
     * Note: Method not in PSR-7
     *
     * @return URI
     */
    public function use_raw_query_string(bool $raw = true)
    {
        $this->raw_query_string = $raw;
        return $this;
    }
    /**
     * Sets and overwrites any current URI information.
     *
     * @return URI
     *
     * @throws HTTPException
     *
     * @deprecated 4.4.0 This method will be private.
     */
    public function set_uri(?string $uri = null)
    {
        if ($uri === null) {
            return $this;
        }
        $parts = parse_url($uri);
        if (is_array($parts)) {
            $this->apply_parts($parts);
            return $this;
        }
        if ($this->silent) {
            return $this;
        }
        throw Http_Exception::for_unable_to_parse_uri($uri);
    }
    /**
     * Retrieve the scheme component of the URI.
     *
     * If no scheme is present, this method MUST return an empty string.
     *
     * The value returned MUST be normalized to lowercase, per RFC 3986
     * Section 3.1.
     *
     * The trailing ":" character is not part of the scheme and MUST NOT be
     * added.
     *
     * @see    https://tools.ietf.org/html/rfc3986#section-3.1
     *
     * @return string The URI scheme.
     */
    public function get_scheme(): string
    {
        return $this->scheme;
    }
    /**
     * Retrieve the authority component of the URI.
     *
     * If no authority information is present, this method MUST return an empty
     * string.
     *
     * The authority syntax of the URI is:
     *
     * <pre>
     * [user-info@]host[:port]
     * </pre>
     *
     * If the port component is not set or is the standard port for the current
     * scheme, it SHOULD NOT be included.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2
     *
     * @return string The URI authority, in "[user-info@]host[:port]" format.
     */
    public function get_authority(bool $ignore_port = false): string
    {
        if ((string) $this->host === '') {
            return '';
        }
        $authority = $this->host;
        if ((string) $this->get_user_info() !== '') {
            $authority = $this->get_user_info() . '@' . $authority;
        }
        // Don't add port if it's a standard port for this scheme
        if ((int) $this->port !== 0 && !$ignore_port && $this->port !== ($this->default_ports[$this->scheme] ?? null)) {
            $authority .= ':' . $this->port;
        }
        $this->show_password = false;
        return $authority;
    }
    /**
     * Retrieve the user information component of the URI.
     *
     * If no user information is present, this method MUST return an empty
     * string.
     *
     * If a user is present in the URI, this will return that value;
     * additionally, if the password is also present, it will be appended to the
     * user value, with a colon (":") separating the values.
     *
     * NOTE that be default, the password, if available, will NOT be shown
     * as a security measure as discussed in RFC 3986, Section 7.5. If you know
     * the password is not a security issue, you can force it to be shown
     * with $this->showPassword();
     *
     * The trailing "@" character is not part of the user information and MUST
     * NOT be added.
     *
     * @return string|null The URI user information, in "username[:password]" format.
     */
    public function get_user_info()
    {
        $user_info = $this->user;
        if ($this->show_password === true && (string) $this->password !== '') {
            $user_info .= ':' . $this->password;
        }
        return $user_info;
    }
    /**
     * Temporarily sets the URI to show a password in userInfo. Will
     * reset itself after the first call to authority().
     *
     * Note: Method not in PSR-7
     *
     * @return URI
     */
    public function show_password(bool $val = true)
    {
        $this->show_password = $val;
        return $this;
    }
    /**
     * Retrieve the host component of the URI.
     *
     * If no host is present, this method MUST return an empty string.
     *
     * The value returned MUST be normalized to lowercase, per RFC 3986
     * Section 3.2.2.
     *
     * @see    http://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * @return string The URI host.
     */
    public function get_host(): string
    {
        return $this->host ?? '';
    }
    /**
     * Retrieve the port component of the URI.
     *
     * If a port is present, and it is non-standard for the current scheme,
     * this method MUST return it as an integer. If the port is the standard port
     * used with the current scheme, this method SHOULD return null.
     *
     * If no port is present, and no scheme is present, this method MUST return
     * a null value.
     *
     * If no port is present, but a scheme is present, this method MAY return
     * the standard port for that scheme, but SHOULD return null.
     *
     * @return int|null The URI port.
     */
    public function get_port()
    {
        return $this->port;
    }
    /**
     * Retrieve the path component of the URI.
     *
     * The path can either be empty or absolute (starting with a slash) or
     * rootless (not starting with a slash). Implementations MUST support all
     * three syntaxes.
     *
     * Normally, the empty path "" and absolute path "/" are considered equal as
     * defined in RFC 7230 Section 2.7.3. But this method MUST NOT automatically
     * do this normalization because in contexts with a trimmed base path, e.g.
     * the front controller, this difference becomes significant. It's the task
     * of the user to handle both "" and "/".
     *
     * The value returned MUST be percent-encoded, but MUST NOT double-encode
     * any characters. To determine what characters to encode, please refer to
     * RFC 3986, Sections 2 and 3.3.
     *
     * As an example, if the value should include a slash ("/") not intended as
     * delimiter between path segments, that value MUST be passed in encoded
     * form (e.g., "%2F") to the instance.
     *
     * @see    https://tools.ietf.org/html/rfc3986#section-2
     * @see    https://tools.ietf.org/html/rfc3986#section-3.3
     *
     * @return string The URI path.
     */
    public function get_path(): string
    {
        return $this->path ?? '';
    }
    /**
     * Retrieve the query string
     *
     * @param array{except?: list<string>|string, only?: list<string>|string} $options
     */
    public function get_query(array $options = []): string
    {
        $vars = $this->query;
        if (array_key_exists('except', $options)) {
            if (!is_array($options['except'])) {
                $options['except'] = [$options['except']];
            }
            foreach ($options['except'] as $var) {
                unset($vars[$var]);
            }
        } elseif (array_key_exists('only', $options)) {
            $temp = [];
            if (!is_array($options['only'])) {
                $options['only'] = [$options['only']];
            }
            foreach ($options['only'] as $var) {
                if (array_key_exists($var, $vars)) {
                    $temp[$var] = $vars[$var];
                }
            }
            $vars = $temp;
        }
        return $vars === [] ? '' : http_build_query($vars);
    }
    /**
     * Retrieve a URI fragment
     */
    public function get_fragment(): string
    {
        return $this->fragment ?? '';
    }
    /**
     * Returns the segments of the path as an array.
     *
     * @return array<int, string>
     */
    public function get_segments(): array
    {
        return $this->segments;
    }
    /**
     * Returns the value of a specific segment of the URI path.
     * Allows to get only existing segments or the next one.
     *
     * @param int    $number  Segment number starting at 1
     * @param string $default Default value
     *
     * @return string The value of the segment. If you specify the last +1
     *                segment, the $default value. If you specify the last +2
     *                or more throws HTTPException.
     */
    public function get_segment(int $number, string $default = ''): string
    {
        if ($number < 1) {
            throw Http_Exception::for_uri_segment_out_of_range($number);
        }
        if ($number > count($this->segments) + 1 && !$this->silent) {
            throw Http_Exception::for_uri_segment_out_of_range($number);
        }
        // The segment should treat the array as 1-based for the user
        // but we still have to deal with a zero-based array.
        $number--;
        return $this->segments[$number] ?? $default;
    }
    /**
     * Set the value of a specific segment of the URI path.
     * Allows to set only existing segments or add new one.
     *
     * Note: Method not in PSR-7
     *
     * @param int        $number Segment number starting at 1
     * @param int|string $value
     *
     * @return $this
     */
    public function set_segment(int $number, $value)
    {
        if ($number < 1) {
            throw Http_Exception::for_uri_segment_out_of_range($number);
        }
        if ($number > count($this->segments) + 1) {
            if ($this->silent) {
                return $this;
            }
            throw Http_Exception::for_uri_segment_out_of_range($number);
        }
        // The segment should treat the array as 1-based for the user
        // but we still have to deal with a zero-based array.
        $number--;
        $this->segments[$number] = $value;
        return $this->refresh_path();
    }
    /**
     * Returns the total number of segments.
     *
     * Note: Method not in PSR-7
     */
    public function get_total_segments(): int
    {
        return count($this->segments);
    }
    /**
     * Formats the URI as a string.
     *
     * Warning: For backwards-compatability this method
     * assumes URIs with the same host as baseURL should
     * be relative to the project's configuration.
     * This aspect of __toString() is deprecated and should be avoided.
     */
    public function __toString(): string
    {
        $path = $this->get_path();
        $scheme = $this->get_scheme();
        // If the hosts matches then assume this should be relative to baseURL
        [$scheme, $path] = $this->change_scheme_and_path($scheme, $path);
        return static::create_uri_string(
            $scheme,
            $this->get_authority(),
            $path,
            // Absolute URIs should use a "/" for an empty path
            $this->get_query(),
            $this->get_fragment()
        );
    }
    /**
     * Change the path (and scheme) assuming URIs with the same host as baseURL
     * should be relative to the project's configuration.
     *
     * @return array{string, string}
     *
     * @deprecated This method will be deleted.
     */
    private function change_scheme_and_path(string $scheme, string $path): array
    {
        // Check if this is an internal URI
        $config = config(App::class);
        $base_uri = new self($config->base_url);
        if (str_starts_with($this->get_scheme(), 'http') && $this->get_host() === $base_uri->get_host()) {
            // Check for additional segments
            $base_path = trim($base_uri->get_path(), '/') . '/';
            $trim_path = ltrim($path, '/');
            if ($base_path !== '/' && !str_starts_with($trim_path, $base_path)) {
                $path = $base_path . $trim_path;
            }
            // Check for forced HTTPS
            if ($config->force_global_secure_requests) {
                $scheme = 'https';
            }
        }
        return [$scheme, $path];
    }
    /**
     * Parses the given string and saves the appropriate authority pieces.
     *
     * Note: Method not in PSR-7
     *
     * @return $this
     */
    public function set_authority(string $str)
    {
        $parts = parse_url($str);
        if (!isset($parts['path'])) {
            $parts['path'] = $this->get_path();
        }
        if (!isset($parts['host']) && $parts['path'] !== '') {
            $parts['host'] = $parts['path'];
            unset($parts['path']);
        }
        $this->apply_parts($parts);
        return $this;
    }
    /**
     * Sets the scheme for this URI.
     *
     * Because of the large number of valid schemes we cannot limit this
     * to only http or https.
     *
     * @see https://www.iana.org/assignments/uri-schemes/uri-schemes.xhtml
     *
     * @return $this
     *
     * @deprecated 4.4.0 Use `withScheme()` instead.
     */
    public function set_scheme(string $str)
    {
        $str = strtolower($str);
        $this->scheme = preg_replace('#:(//)?$#', '', $str);
        return $this;
    }
    /**
     * Return an instance with the specified scheme.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified scheme.
     *
     * Implementations MUST support the schemes "http" and "https" case
     * insensitively, and MAY accommodate other schemes if required.
     *
     * An empty scheme is equivalent to removing the scheme.
     *
     * @param string $scheme The scheme to use with the new instance.
     *
     * @return static A new instance with the specified scheme.
     *
     * @throws InvalidArgumentException for invalid or unsupported schemes.
     */
    public function with_scheme(string $scheme)
    {
        $uri = clone $this;
        $scheme = strtolower($scheme);
        $uri->scheme = preg_replace('#:(//)?$#', '', $scheme);
        return $uri;
    }
    /**
     * Sets the userInfo/Authority portion of the URI.
     *
     * @param string $user The user's username
     * @param string $pass The user's password
     *
     * @return $this
     *
     * @TODO PSR-7: Should be `withUserInfo($user, $password = null)`.
     */
    public function set_user_info(
        string $user,
        #[Sensitive_Parameter]
        string $pass
    )
    {
        $this->user = trim($user);
        $this->password = trim($pass);
        return $this;
    }
    /**
     * Sets the host name to use.
     *
     * @return $this
     *
     * @TODO PSR-7: Should be `withHost($host)`.
     */
    public function set_host(string $str)
    {
        $this->host = trim($str);
        return $this;
    }
    /**
     * Sets the port portion of the URI.
     *
     * @return $this
     *
     * @TODO PSR-7: Should be `withPort($port)`.
     */
    public function set_port(?int $port = null)
    {
        if ($port === null) {
            return $this;
        }
        if ($port > 0 && $port <= 65535) {
            $this->port = $port;
            return $this;
        }
        if ($this->silent) {
            return $this;
        }
        throw Http_Exception::for_invalid_port($port);
    }
    /**
     * Sets the path portion of the URI.
     *
     * @return $this
     *
     * @TODO PSR-7: Should be `withPath($port)`.
     */
    public function set_path(string $path)
    {
        $this->path = $this->filter_path($path);
        $temp_path = trim($this->path, '/');
        $this->segments = $temp_path === '' ? [] : explode('/', $temp_path);
        return $this;
    }
    /**
     * Sets the current baseURL.
     *
     * @interal
     *
     * @deprecated Use SiteURI instead.
     */
    public function set_base_url(string $base_url): void
    {
        $this->base_url = $base_url;
    }
    /**
     * Returns the current baseURL.
     *
     * @interal
     *
     * @deprecated Use SiteURI instead.
     */
    public function get_base_url(): string
    {
        if ($this->base_url === null) {
            throw new BadMethodCallException('The $baseURL is not set.');
        }
        return $this->base_url;
    }
    /**
     * Sets the path portion of the URI based on segments.
     *
     * @return $this
     *
     * @deprecated This method will be private.
     */
    public function refresh_path()
    {
        $this->path = $this->filter_path(implode('/', $this->segments));
        $temp_path = trim($this->path, '/');
        $this->segments = $temp_path === '' ? [] : explode('/', $temp_path);
        return $this;
    }
    /**
     * Sets the query portion of the URI, while attempting
     * to clean the various parts of the query keys and values.
     *
     * @return $this
     *
     * @TODO PSR-7: Should be `withQuery($query)`.
     */
    public function set_query(string $query)
    {
        if (str_contains($query, '#')) {
            if ($this->silent) {
                return $this;
            }
            throw Http_Exception::for_malformed_query_string();
        }
        // Can't have leading ?
        if ($query !== '' && str_starts_with($query, '?')) {
            $query = substr($query, 1);
        }
        if ($this->raw_query_string) {
            $this->query = $this->parse_str($query);
        } else {
            parse_str($query, $this->query);
        }
        return $this;
    }
    /**
     * A convenience method to pass an array of items in as the Query
     * portion of the URI.
     *
     * @return URI
     *
     * @TODO: PSR-7: Should be `withQueryParams(array $query)`
     */
    public function set_query_array(array $query)
    {
        $query = http_build_query($query);
        return $this->set_query($query);
    }
    /**
     * Adds a single new element to the query vars.
     *
     * Note: Method not in PSR-7
     *
     * @param int|string|null $value
     *
     * @return $this
     */
    public function add_query(string $key, $value = null)
    {
        $this->query[$key] = $value;
        return $this;
    }
    /**
     * Removes one or more query vars from the URI.
     *
     * Note: Method not in PSR-7
     *
     * @param string ...$params
     *
     * @return $this
     */
    public function strip_query(...$params)
    {
        foreach ($params as $param) {
            unset($this->query[$param]);
        }
        return $this;
    }
    /**
     * Filters the query variables so that only the keys passed in
     * are kept. The rest are removed from the object.
     *
     * Note: Method not in PSR-7
     *
     * @param string ...$params
     *
     * @return $this
     */
    public function keep_query(...$params)
    {
        $temp = [];
        foreach ($this->query as $key => $value) {
            if (!in_array($key, $params, true)) {
                continue;
            }
            $temp[$key] = $value;
        }
        $this->query = $temp;
        return $this;
    }
    /**
     * Sets the fragment portion of the URI.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.5
     *
     * @return $this
     *
     * @TODO PSR-7: Should be `withFragment($fragment)`.
     */
    public function set_fragment(string $string)
    {
        $this->fragment = trim($string, '# ');
        return $this;
    }
    /**
     * Encodes any dangerous characters, and removes dot segments.
     * While dot segments have valid uses according to the spec,
     * this URI class does not allow them.
     */
    protected function filter_path(?string $path = null): string
    {
        $orig = $path;
        // Decode/normalize percent-encoded chars so
        // we can always have matching for Routes, etc.
        $path = urldecode($path);
        // Remove dot segments
        $path = self::remove_dot_segments($path);
        // Fix up some leading slash edge cases...
        if (str_starts_with($orig, './')) {
            $path = '/' . $path;
        }
        if (str_starts_with($orig, '../')) {
            $path = '/' . $path;
        }
        // Encode characters
        $path = preg_replace_callback('/(?:[^' . static::CHAR_UNRESERVED . ':@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/', static fn(array $matches): string => rawurlencode($matches[0]), $path);
        return $path;
    }
    /**
     * Saves our parts from a parse_url call.
     *
     * @param array{
     *  host?: string,
     *  user?: string,
     *  path?: string,
     *  query?: string,
     *  fragment?: string,
     *  scheme?: string,
     *  port?: int,
     *  pass?: string,
     * } $parts
     *
     * @return void
     */
    protected function apply_parts(array $parts)
    {
        if (isset($parts['host']) && $parts['host'] !== '') {
            $this->host = $parts['host'];
        }
        if (isset($parts['user']) && $parts['user'] !== '') {
            $this->user = $parts['user'];
        }
        if (isset($parts['path']) && $parts['path'] !== '') {
            $this->path = $this->filter_path($parts['path']);
        }
        if (isset($parts['query']) && $parts['query'] !== '') {
            $this->set_query($parts['query']);
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $this->fragment = $parts['fragment'];
        }
        if (isset($parts['scheme'])) {
            $this->set_scheme(rtrim($parts['scheme'], ':/'));
        } else {
            $this->set_scheme('http');
        }
        if (isset($parts['port'])) {
            // Valid port numbers are enforced by earlier parse_url or setPort()
            $this->port = $parts['port'];
        }
        if (isset($parts['pass'])) {
            $this->password = $parts['pass'];
        }
        if (isset($parts['path']) && $parts['path'] !== '') {
            $temp_path = trim($parts['path'], '/');
            $this->segments = $temp_path === '' ? [] : explode('/', $temp_path);
        }
    }
    /**
     * Combines one URI string with this one based on the rules set out in
     * RFC 3986 Section 2
     *
     * @see http://tools.ietf.org/html/rfc3986#section-5.2
     *
     * @return URI
     */
    public function resolve_relative_uri(string $uri)
    {
        /*
         * NOTE: We don't use removeDotSegments in this
         * algorithm since it's already done by this line!
         */
        $relative = new self();
        $relative->set_uri($uri);
        if ($relative->get_scheme() === $this->get_scheme()) {
            $relative->set_scheme('');
        }
        $transformed = clone $relative;
        // 5.2.2 Transform References in a non-strict method (no scheme)
        if ($relative->get_authority() !== '') {
            $transformed->set_authority($relative->get_authority())->set_path($relative->get_path())->set_query($relative->get_query());
        } else {
            if ($relative->get_path() === '') {
                $transformed->set_path($this->get_path());
                if ($relative->get_query() !== '') {
                    $transformed->set_query($relative->get_query());
                } else {
                    $transformed->set_query($this->get_query());
                }
            } else {
                if (str_starts_with($relative->get_path(), '/')) {
                    $transformed->set_path($relative->get_path());
                } else {
                    $transformed->set_path($this->merge_paths($this, $relative));
                }
                $transformed->set_query($relative->get_query());
            }
            $transformed->set_authority($this->get_authority());
        }
        $transformed->set_scheme($this->get_scheme());
        $transformed->set_fragment($relative->get_fragment());
        return $transformed;
    }
    /**
     * Given 2 paths, will merge them according to rules set out in RFC 2986,
     * Section 5.2
     *
     * @see http://tools.ietf.org/html/rfc3986#section-5.2.3
     */
    protected function merge_paths(self $base, self $reference): string
    {
        if ($base->get_authority() !== '' && $base->get_path() === '') {
            return '/' . ltrim($reference->get_path(), '/ ');
        }
        $path = explode('/', $base->get_path());
        if ($path[0] === '') {
            unset($path[0]);
        }
        array_pop($path);
        $path[] = $reference->get_path();
        return implode('/', $path);
    }
    /**
     * This is equivalent to the native PHP parse_str() function.
     * This version allows the dot to be used as a key of the query string.
     *
     * @return array<string, string>
     */
    protected function parse_str(string $query): array
    {
        $return = [];
        $query = explode('&', $query);
        $params = array_map(static fn(string $chunk): ?string => preg_replace_callback('/^(?<key>[^&=]+?)(?:\[[^&=]*\])?=(?<value>[^&=]+)/', static fn(array $match): string => str_replace($match['key'], bin2hex($match['key']), $match[0]), urldecode($chunk)), $query);
        $params = implode('&', $params);
        parse_str($params, $result);
        foreach ($result as $key => $value) {
            // Array key might be int
            $return[hex2bin((string) $key)] = $value;
        }
        return $return;
    }
}