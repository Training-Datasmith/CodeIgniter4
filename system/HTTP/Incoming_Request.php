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
use Code_Igniter\HTTP\Files\File_Collection;
use Code_Igniter\HTTP\Files\Uploaded_File;
use Config\App;
use Config\Services;
use Locale;
use stdClass;
/**
 * Class IncomingRequest
 *
 * Represents an incoming, server-side HTTP request.
 *
 * Per the HTTP specification, this interface includes properties for
 * each of the following:
 *
 * - Protocol version
 * - HTTP method
 * - URI
 * - Headers
 * - Message body
 *
 * Additionally, it encapsulates all data as it has arrived to the
 * application from the CGI and/or PHP environment, including:
 *
 * - The values represented in $_SERVER.
 * - Any cookies provided (generally via $_COOKIE)
 * - Query string arguments (generally via $_GET, or as parsed via parse_str())
 * - Upload files, if any (as represented by $_FILES)
 * - Deserialized body binds (generally from $_POST)
 *
 * @see \CodeIgniter\HTTP\IncomingRequestTest
 */
class Incoming_Request extends Request
{
    /**
     * The URI for this request.
     *
     * Note: This WILL NOT match the actual URL in the browser since for
     * everything this cares about (and the router, etc) is the portion
     * AFTER the baseURL. So, if hosted in a sub-folder this will
     * appear different than actual URI path. If you need that use getPath().
     *
     * @var URI
     */
    protected $uri;
    /**
     * The detected URI path (relative to the baseURL).
     *
     * Note: current_url() uses this to build its URI,
     * so this becomes the source for the "current URL"
     * when working with the share request instance.
     *
     * @var string|null
     */
    protected $path;
    /**
     * File collection
     *
     * @var FileCollection|null
     */
    protected $files;
    /**
     * Negotiator
     *
     * @var Negotiate|null
     */
    protected $negotiator;
    /**
     * The default Locale this request
     * should operate under.
     *
     * @var string
     */
    protected $default_locale;
    /**
     * The current locale of the application.
     * Default value is set in app/Config/App.php
     *
     * @var string
     */
    protected $locale;
    /**
     * Stores the valid locale codes.
     *
     * @var array
     */
    protected $valid_locales = [];
    /**
     * Holds the old data from a redirect.
     *
     * @var array
     */
    protected $old_input = [];
    /**
     * The user agent this request is from.
     *
     * @var UserAgent
     */
    protected $user_agent;
    /**
     * Constructor
     *
     * @param App         $config
     * @param string|null $body
     */
    public function __construct($config, ?URI $uri = null, $body = 'php://input', ?User_Agent $user_agent = null)
    {
        if (!$uri instanceof URI || !$user_agent instanceof User_Agent) {
            throw new InvalidArgumentException('You must supply the parameters: uri, userAgent.');
        }
        $this->populate_headers();
        if ($body === 'php://input' && !str_contains($this->get_header_line('Content-Type'), 'multipart/form-data') && (int) $this->get_header_line('Content-Length') <= $this->get_post_max_size()) {
            // Get our body from php://input
            $body = file_get_contents('php://input');
        }
        // If file_get_contents() returns false or empty string, set null.
        if ($body === false || $body === '') {
            $body = null;
        }
        $this->uri = $uri;
        $this->body = $body;
        $this->user_agent = $user_agent;
        $this->valid_locales = $config->supported_locales;
        parent::__construct($config);
        if ($uri instanceof Site_Uri) {
            $this->set_path($uri->get_route_path());
        } else {
            $this->set_path($uri->get_path());
        }
        $this->detect_locale($config);
    }
    private function get_post_max_size(): int
    {
        $post_max_size = ini_get('post_max_size');
        return match (strtoupper(substr($post_max_size, -1))) {
            'G' => (int) str_replace('G', '', $post_max_size) * 1024 ** 3,
            'M' => (int) str_replace('M', '', $post_max_size) * 1024 ** 2,
            'K' => (int) str_replace('K', '', $post_max_size) * 1024,
            default => (int) $post_max_size,
        };
    }
    /**
     * Handles setting up the locale, perhaps auto-detecting through
     * content negotiation.
     *
     * @param App $config
     *
     * @return void
     */
    public function detect_locale($config)
    {
        $this->locale = $this->default_locale = $config->default_locale;
        if (!$config->negotiate_locale) {
            return;
        }
        $this->set_locale($this->negotiate('language', $config->supported_locales));
    }
    /**
     * Provides a convenient way to work with the Negotiate class
     * for content negotiation.
     */
    public function negotiate(string $type, array $supported, bool $strict_match = false): string
    {
        if ($this->negotiator === null) {
            $this->negotiator = Services::negotiator($this, true);
        }
        return match (strtolower($type)) {
            'media' => $this->negotiator->media($supported, $strict_match),
            'charset' => $this->negotiator->charset($supported),
            'encoding' => $this->negotiator->encoding($supported),
            'language' => $this->negotiator->language($supported),
            default => throw Http_Exception::for_invalid_negotiation_type($type),
        };
    }
    /**
     * Checks this request type.
     */
    public function is(string $type): bool
    {
        $value_upper = strtoupper($type);
        $http_methods = Method::all();
        if (in_array($value_upper, $http_methods, true)) {
            return $this->get_method() === $value_upper;
        }
        if ($value_upper === 'JSON') {
            return str_contains($this->get_header_line('Content-Type'), 'application/json');
        }
        if ($value_upper === 'AJAX') {
            return $this->is_ajax();
        }
        throw new InvalidArgumentException('Unknown type: ' . $type);
    }
    /**
     * Determines if this request was made from the command line (CLI).
     */
    public function is_cli(): bool
    {
        return false;
    }
    /**
     * Test to see if a request contains the HTTP_X_REQUESTED_WITH header.
     */
    public function is_ajax(): bool
    {
        return $this->has_header('X-Requested-With') && strtolower($this->header('X-Requested-With')->get_value()) === 'xmlhttprequest';
    }
    /**
     * Attempts to detect if the current connection is secure through
     * a few different methods.
     */
    public function is_secure(): bool
    {
        $https = service('superglobals')->server('HTTPS');
        if ($https !== null && strtolower($https) !== 'off') {
            return true;
        }
        if ($this->has_header('X-Forwarded-Proto') && $this->header('X-Forwarded-Proto')->get_value() === 'https') {
            return true;
        }
        return $this->has_header('Front-End-Https') && !empty($this->header('Front-End-Https')->get_value()) && strtolower($this->header('Front-End-Https')->get_value()) !== 'off';
    }
    /**
     * Sets the URI path relative to baseURL.
     *
     * Note: Since current_url() accesses the shared request
     * instance, this can be used to change the "current URL"
     * for testing.
     *
     * @param string $path URI path relative to baseURL
     *
     * @return $this
     */
    private function set_path(string $path)
    {
        $this->path = $path;
        return $this;
    }
    /**
     * Returns the URI path relative to baseURL,
     * running detection as necessary.
     */
    public function get_path(): string
    {
        return $this->path;
    }
    /**
     * Sets the locale string for this request.
     *
     * @return IncomingRequest
     */
    public function set_locale(string $locale)
    {
        // If it's not a valid locale, set it
        // to the default locale for the site.
        if (!in_array($locale, $this->valid_locales, true)) {
            $locale = $this->default_locale;
        }
        $this->locale = $locale;
        Locale::set_default($locale);
        return $this;
    }
    /**
     * Set the valid locales.
     *
     * @return $this
     */
    public function set_valid_locales(array $locales)
    {
        $this->valid_locales = $locales;
        return $this;
    }
    /**
     * Gets the current locale, with a fallback to the default
     * locale if none is set.
     */
    public function get_locale(): string
    {
        return $this->locale;
    }
    /**
     * Returns the default locale as set in app/Config/App.php
     */
    public function get_default_locale(): string
    {
        return $this->default_locale;
    }
    /**
     * Fetch an item from JSON input stream with fallback to $_REQUEST object. This is the simplest way
     * to grab data from the request object and can be used in lieu of the
     * other get* methods in most cases.
     *
     * @param array|string|null $index
     * @param int|null          $filter Filter constant
     * @param array|int|null    $flags
     *
     * @return array|bool|float|int|stdClass|string|null
     */
    public function get_var($index = null, $filter = null, $flags = null)
    {
        if (str_contains($this->get_header_line('Content-Type'), 'application/json') && $this->body !== null) {
            return $this->get_json_var($index, false, $filter, $flags);
        }
        return $this->fetch_global('request', $index, $filter, $flags);
    }
    /**
     * A convenience method that grabs the raw input stream and decodes
     * the JSON into an array.
     *
     * If $assoc == true, then all objects in the response will be converted
     * to associative arrays.
     *
     * @param bool $assoc   Whether to return objects as associative arrays
     * @param int  $depth   How many levels deep to decode
     * @param int  $options Bitmask of options
     *
     * @see http://php.net/manual/en/function.json-decode.php
     *
     * @return array|bool|float|int|stdClass|null
     *
     * @throws HTTPException When the body is invalid as JSON.
     */
    public function get_json(bool $assoc = false, int $depth = 512, int $options = 0)
    {
        if ($this->body === null) {
            return null;
        }
        $result = json_decode($this->body, $assoc, $depth, $options);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw Http_Exception::for_invalid_json(json_last_error_msg());
        }
        return $result;
    }
    /**
     * Get a specific variable from a JSON input stream
     *
     * @param array|string|null $index  The variable that you want which can use dot syntax for getting specific values.
     * @param bool              $assoc  If true, return the result as an associative array.
     * @param int|null          $filter Filter Constant
     * @param array|int|null    $flags  Option
     *
     * @return array|bool|float|int|stdClass|string|null
     */
    public function get_json_var($index = null, bool $assoc = false, ?int $filter = null, $flags = null)
    {
        helper('array');
        $data = $this->get_json(true);
        if (!is_array($data)) {
            return null;
        }
        if (is_string($index)) {
            $data = dot_array_search($index, $data);
        } elseif (is_array($index)) {
            $result = [];
            foreach ($index as $key) {
                $result[$key] = dot_array_search($key, $data);
            }
            [$data, $result] = [$result, null];
        }
        if ($data === null) {
            return null;
        }
        $filter ??= FILTER_UNSAFE_RAW;
        $flags = is_array($flags) ? $flags : (is_numeric($flags) ? (int) $flags : 0);
        if ($filter !== FILTER_UNSAFE_RAW || (is_numeric($flags) && $flags !== 0 || is_array($flags) && $flags !== [])) {
            if (is_array($data)) {
                // Iterate over array and append filter and flags
                array_walk_recursive($data, static function (&$val) use ($filter, $flags): void {
                    $val_type = gettype($val);
                    $val = filter_var($val, $filter, $flags);
                    if (in_array($val_type, ['int', 'integer', 'float', 'double', 'bool', 'boolean'], true) && $val !== false) {
                        settype($val, $val_type);
                    }
                });
            } else {
                $data_type = gettype($data);
                $data = filter_var($data, $filter, $flags);
                if (in_array($data_type, ['int', 'integer', 'float', 'double', 'bool', 'boolean'], true) && $data !== false) {
                    settype($data, $data_type);
                }
            }
        }
        if (!$assoc) {
            if (is_array($index)) {
                foreach ($data as &$val) {
                    $val = is_array($val) ? json_decode(json_encode($val)) : $val;
                }
                return $data;
            }
            return json_decode(json_encode($data));
        }
        return $data;
    }
    /**
     * A convenience method that grabs the raw input stream(send method in PUT, PATCH, DELETE) and decodes
     * the String into an array.
     *
     * @return array
     */
    public function get_raw_input()
    {
        parse_str($this->body ?? '', $output);
        return $output;
    }
    /**
     * Gets a specific variable from raw input stream (send method in PUT, PATCH, DELETE).
     *
     * @param array|string|null $index  The variable that you want which can use dot syntax for getting specific values.
     * @param int|null          $filter Filter Constant
     * @param array|int|null    $flags  Option
     *
     * @return array|bool|float|int|object|string|null
     */
    public function get_raw_input_var($index = null, ?int $filter = null, $flags = null)
    {
        helper('array');
        parse_str($this->body ?? '', $output);
        if (is_string($index)) {
            $output = dot_array_search($index, $output);
        } elseif (is_array($index)) {
            $data = [];
            foreach ($index as $key) {
                $data[$key] = dot_array_search($key, $output);
            }
            [$output, $data] = [$data, null];
        }
        $filter ??= FILTER_UNSAFE_RAW;
        $flags = is_array($flags) ? $flags : (is_numeric($flags) ? (int) $flags : 0);
        if (is_array($output) && ($filter !== FILTER_UNSAFE_RAW || (is_numeric($flags) && $flags !== 0 || is_array($flags) && $flags !== []))) {
            // Iterate over array and append filter and flags
            array_walk_recursive($output, static function (&$val) use ($filter, $flags): void {
                $val = filter_var($val, $filter, $flags);
            });
            return $output;
        }
        if (is_string($output)) {
            return filter_var($output, $filter, $flags);
        }
        return $output;
    }
    /**
     * Fetch an item from GET data.
     *
     * @param array|string|null $index  Index for item to fetch from $_GET.
     * @param int|null          $filter A filter name to apply.
     * @param array|int|null    $flags
     *
     * @return array|bool|float|int|object|string|null
     */
    public function get_get($index = null, $filter = null, $flags = null)
    {
        return $this->fetch_global('get', $index, $filter, $flags);
    }
    /**
     * Fetch an item from POST.
     *
     * @param array|string|null $index  Index for item to fetch from $_POST.
     * @param int|null          $filter A filter name to apply
     * @param array|int|null    $flags
     *
     * @return array|bool|float|int|object|string|null
     */
    public function get_post($index = null, $filter = null, $flags = null)
    {
        return $this->fetch_global('post', $index, $filter, $flags);
    }
    /**
     * Fetch an item from POST data with fallback to GET.
     *
     * @param array|string|null $index  Index for item to fetch from $_POST or $_GET
     * @param int|null          $filter A filter name to apply
     * @param array|int|null    $flags
     *
     * @return array|bool|float|int|object|string|null
     */
    public function get_post_get($index = null, $filter = null, $flags = null)
    {
        if ($index === null) {
            return array_merge($this->get_get($index, $filter, $flags), $this->get_post($index, $filter, $flags));
        }
        // Use $_POST directly here, since filter_has_var only
        // checks the initial POST data, not anything that might
        // have been added since.
        return service('superglobals')->post($index) !== null ? $this->get_post($index, $filter, $flags) : (service('superglobals')->get($index) !== null ? $this->get_get($index, $filter, $flags) : $this->get_post($index, $filter, $flags));
    }
    /**
     * Fetch an item from GET data with fallback to POST.
     *
     * @param array|string|null $index  Index for item to be fetched from $_GET or $_POST
     * @param int|null          $filter A filter name to apply
     * @param array|int|null    $flags
     *
     * @return array|bool|float|int|object|string|null
     */
    public function get_get_post($index = null, $filter = null, $flags = null)
    {
        if ($index === null) {
            return array_merge($this->get_post($index, $filter, $flags), $this->get_get($index, $filter, $flags));
        }
        // Use $_GET directly here, since filter_has_var only
        // checks the initial GET data, not anything that might
        // have been added since.
        return service('superglobals')->get($index) !== null ? $this->get_get($index, $filter, $flags) : (service('superglobals')->post($index) !== null ? $this->get_post($index, $filter, $flags) : $this->get_get($index, $filter, $flags));
    }
    /**
     * Fetch an item from the COOKIE array.
     *
     * @param array|string|null $index  Index for item to be fetched from $_COOKIE
     * @param int|null          $filter A filter name to be applied
     * @param array|int|null    $flags
     *
     * @return array|bool|float|int|object|string|null
     */
    public function get_cookie($index = null, $filter = null, $flags = null)
    {
        return $this->fetch_global('cookie', $index, $filter, $flags);
    }
    /**
     * Fetch the user agent string
     *
     * @return UserAgent
     */
    public function get_user_agent()
    {
        return $this->user_agent;
    }
    /**
     * Attempts to get old Input data that has been flashed to the session
     * with redirect_with_input(). It first checks for the data in the old
     * POST data, then the old GET data and finally check for dot arrays
     *
     * @return array|string|null
     */
    public function get_old_input(string $key)
    {
        // If the session hasn't been started, we're done.
        if (!isset($_SESSION)) {
            return null;
        }
        // Get previously saved in session
        $old = session('_ci_old_input');
        // If no data was previously saved, we're done.
        if ($old === null) {
            return null;
        }
        // Check for the value in the POST array first.
        if (isset($old['post'][$key])) {
            return $old['post'][$key];
        }
        // Next check in the GET array.
        if (isset($old['get'][$key])) {
            return $old['get'][$key];
        }
        helper('array');
        // Check for an array value in POST.
        if (isset($old['post'])) {
            $value = dot_array_search($key, $old['post']);
            if ($value !== null) {
                return $value;
            }
        }
        // Check for an array value in GET.
        if (isset($old['get'])) {
            $value = dot_array_search($key, $old['get']);
            if ($value !== null) {
                return $value;
            }
        }
        // requested session key not found
        return null;
    }
    /**
     * Returns an array of all files that have been uploaded with this
     * request. Each file is represented by an UploadedFile instance.
     */
    public function get_files(): array
    {
        if ($this->files === null) {
            $this->files = new File_Collection();
        }
        return $this->files->all();
        // return all files
    }
    /**
     * Verify if a file exist, by the name of the input field used to upload it, in the collection
     * of uploaded files and if is have been uploaded with multiple option.
     *
     * @return array|null
     */
    public function get_file_multiple(string $file_id)
    {
        if ($this->files === null) {
            $this->files = new File_Collection();
        }
        return $this->files->get_file_multiple($file_id);
    }
    /**
     * Retrieves a single file by the name of the input field used
     * to upload it.
     *
     * @return UploadedFile|null
     */
    public function get_file(string $file_id)
    {
        if ($this->files === null) {
            $this->files = new File_Collection();
        }
        return $this->files->get_file($file_id);
    }
}