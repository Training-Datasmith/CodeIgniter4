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
namespace Code_Igniter\Security;

use Code_Igniter\Cookie\Cookie;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\Exceptions\LogicException;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Method;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\I18n\Time;
use Code_Igniter\Security\Exceptions\Security_Exception;
use Code_Igniter\Session\Session;
use Config\Cookie as CookieConfig;
use Config\Security as SecurityConfig;
use ErrorException;
use Json_Exception;
use Sensitive_Parameter;
/**
 * Class Security
 *
 * Provides methods that help protect your site against
 * Cross-Site Request Forgery attacks.
 *
 * @see \CodeIgniter\Security\SecurityTest
 */
class Security implements Security_Interface
{
    public const CSRF_PROTECTION_COOKIE = 'cookie';
    public const CSRF_PROTECTION_SESSION = 'session';
    protected const CSRF_HASH_BYTES = 16;
    /**
     * CSRF Protection Method
     *
     * Protection Method for Cross Site Request Forgery protection.
     *
     * @var string 'cookie' or 'session'
     *
     * @deprecated 4.4.0 Use $this->config->csrfProtection.
     */
    protected $csrf_protection = self::CSRF_PROTECTION_COOKIE;
    /**
     * CSRF Token Randomization
     *
     * @var bool
     *
     * @deprecated 4.4.0 Use $this->config->tokenRandomize.
     */
    protected $token_randomize = false;
    /**
     * CSRF Hash (without randomization)
     *
     * Random hash for Cross Site Request Forgery protection.
     *
     * @var string|null
     */
    protected $hash;
    /**
     * CSRF Token Name
     *
     * Token name for Cross Site Request Forgery protection.
     *
     * @var string
     *
     * @deprecated 4.4.0 Use $this->config->tokenName.
     */
    protected $token_name = 'csrf_token_name';
    /**
     * CSRF Header Name
     *
     * Header name for Cross Site Request Forgery protection.
     *
     * @var string
     *
     * @deprecated 4.4.0 Use $this->config->headerName.
     */
    protected $header_name = 'X-CSRF-TOKEN';
    /**
     * The CSRF Cookie instance.
     *
     * @var Cookie
     */
    protected $cookie;
    /**
     * CSRF Cookie Name (with Prefix)
     *
     * Cookie name for Cross Site Request Forgery protection.
     *
     * @var string
     */
    protected $cookie_name = 'csrf_cookie_name';
    /**
     * CSRF Expires
     *
     * Expiration time for Cross Site Request Forgery protection cookie.
     *
     * Defaults to two hours (in seconds).
     *
     * @var int
     *
     * @deprecated 4.4.0 Use $this->config->expires.
     */
    protected $expires = 7200;
    /**
     * CSRF Regenerate
     *
     * Regenerate CSRF Token on every request.
     *
     * @var bool
     *
     * @deprecated 4.4.0 Use $this->config->regenerate.
     */
    protected $regenerate = true;
    /**
     * CSRF Redirect
     *
     * Redirect to previous page with error on failure.
     *
     * @var bool
     *
     * @deprecated 4.4.0 Use $this->config->redirect.
     */
    protected $redirect = false;
    /**
     * CSRF SameSite
     *
     * Setting for CSRF SameSite cookie token.
     *
     * Allowed values are: None - Lax - Strict - ''.
     *
     * Defaults to `Lax` as recommended in this link:
     *
     * @see https://portswigger.net/web-security/csrf/samesite-cookies
     *
     * @var string
     *
     * @deprecated `Config\Cookie` $samesite property is used.
     */
    protected $samesite = Cookie::SAMESITE_LAX;
    private readonly Incoming_Request $request;
    /**
     * CSRF Cookie Name without Prefix
     */
    private ?string $raw_cookie_name = null;
    /**
     * Session instance.
     */
    private ?Session $session = null;
    /**
     * CSRF Hash in Request Cookie
     *
     * The cookie value is always CSRF hash (without randomization) even if
     * $tokenRandomize is true.
     */
    private ?string $hash_in_cookie = null;
    /**
     * Security Config
     */
    protected Security_Config $config;
    /**
     * Constructor.
     *
     * Stores our configuration and fires off the init() method to setup
     * initial state.
     */
    public function __construct(Security_Config $config)
    {
        $this->config = $config;
        $this->raw_cookie_name = $config->cookie_name;
        if ($this->is_csrf_cookie()) {
            $cookie = config(Cookie_Config::class);
            $this->configure_cookie($cookie);
        } else {
            // Session based CSRF protection
            $this->configure_session();
        }
        $this->request = service('request');
        $this->hash_in_cookie = $this->request->get_cookie($this->cookie_name);
        $this->restore_hash();
        if ($this->hash === null) {
            $this->generate_hash();
        }
    }
    private function is_csrf_cookie(): bool
    {
        return $this->config->csrf_protection === self::CSRF_PROTECTION_COOKIE;
    }
    private function configure_session(): void
    {
        $this->session = service('session');
    }
    private function configure_cookie(Cookie_Config $cookie): void
    {
        $cookie_prefix = $cookie->prefix;
        $this->cookie_name = $cookie_prefix . $this->raw_cookie_name;
        Cookie::set_defaults($cookie);
    }
    public function verify(Request_Interface $request)
    {
        $method = $request->get_method();
        // Protect POST, PUT, DELETE, PATCH requests only
        if (!in_array($method, [Method::POST, Method::PUT, Method::DELETE, Method::PATCH], true)) {
            return $this;
        }
        assert($request instanceof Incoming_Request);
        $posted_token = $this->get_posted_token($request);
        try {
            $token = $posted_token !== null && $this->config->token_randomize ? $this->derandomize($posted_token) : $posted_token;
        } catch (InvalidArgumentException) {
            $token = null;
        }
        if (!isset($token, $this->hash) || !hash_equals($this->hash, $token)) {
            throw Security_Exception::for_disallowed_action();
        }
        $this->remove_token_in_request($request);
        if ($this->config->regenerate) {
            $this->generate_hash();
        }
        log_message('info', 'CSRF token verified.');
        return $this;
    }
    /**
     * Remove token in POST or JSON request data
     */
    private function remove_token_in_request(Incoming_Request $request): void
    {
        $superglobals = service('superglobals');
        $token_name = $this->config->token_name;
        // If the token is found in POST data, we can safely remove it.
        if (is_string($superglobals->post($token_name))) {
            $superglobals->unset_post($token_name);
            $request->set_global('post', $superglobals->get_post_array());
            return;
        }
        $body = $request->get_body() ?? '';
        if ($body === '') {
            return;
        }
        // If the token is found in JSON data, we can safely remove it.
        try {
            $json = json_decode($body, flags: JSON_THROW_ON_ERROR);
        } catch (Json_Exception) {
            $json = null;
        }
        if (is_object($json) && property_exists($json, $token_name)) {
            unset($json->{$token_name});
            $request->set_body(json_encode($json));
            return;
        }
        // If the token is found in form-encoded data, we can safely remove it.
        parse_str($body, $result);
        unset($result[$token_name]);
        $request->set_body(http_build_query($result));
    }
    private function get_posted_token(Incoming_Request $request): ?string
    {
        $token_name = $this->config->token_name;
        $header_name = $this->config->header_name;
        // 1. Check POST data first.
        $token = $request->get_post($token_name);
        if ($this->is_non_empty_token_string($token)) {
            return $token;
        }
        // 2. Check header data next.
        if ($request->has_header($header_name)) {
            $token = $request->header($header_name)->get_value();
            if ($this->is_non_empty_token_string($token)) {
                return $token;
            }
        }
        // 3. Finally, check the raw input data for JSON or form-encoded data.
        $body = $request->get_body() ?? '';
        if ($body === '') {
            return null;
        }
        // 3a. Check if a JSON payload exists and contains the token.
        try {
            $json = json_decode($body, flags: JSON_THROW_ON_ERROR);
        } catch (Json_Exception) {
            $json = null;
        }
        if (is_object($json) && property_exists($json, $token_name)) {
            $token = $json->{$token_name};
            if ($this->is_non_empty_token_string($token)) {
                return $token;
            }
        }
        // 3b. Check if form-encoded data exists and contains the token.
        parse_str($body, $result);
        $token = $result[$token_name] ?? null;
        if ($this->is_non_empty_token_string($token)) {
            return $token;
        }
        return null;
    }
    /**
     * @phpstan-assert-if-true non-empty-string $token
     */
    private function is_non_empty_token_string(mixed $token): bool
    {
        return is_string($token) && $token !== '';
    }
    /**
     * Returns the CSRF Token.
     */
    public function get_hash(): ?string
    {
        return $this->config->token_randomize ? $this->randomize($this->hash) : $this->hash;
    }
    /**
     * Randomize hash to avoid BREACH attacks.
     *
     * @params string $hash CSRF hash
     *
     * @return string CSRF token
     */
    protected function randomize(string $hash): string
    {
        $key_binary = random_bytes(static::CSRF_HASH_BYTES);
        $hash_binary = hex2bin($hash);
        if ($hash_binary === false) {
            throw new LogicException('$hash is invalid: ' . $hash);
        }
        return bin2hex(($hash_binary ^ $key_binary) . $key_binary);
    }
    /**
     * Derandomize the token.
     *
     * @params string $token CSRF token
     *
     * @return string CSRF hash
     *
     * @throws InvalidArgumentException "hex2bin(): Hexadecimal input string must have an even length"
     */
    protected function derandomize(
        #[Sensitive_Parameter]
        string $token
    ): string
    {
        $key = substr($token, -static::CSRF_HASH_BYTES * 2);
        $value = substr($token, 0, static::CSRF_HASH_BYTES * 2);
        try {
            return bin2hex((string) hex2bin($value) ^ (string) hex2bin($key));
        } catch (ErrorException $e) {
            // "hex2bin(): Hexadecimal input string must have an even length"
            throw new InvalidArgumentException($e->get_message(), $e->get_code(), $e);
        }
    }
    /**
     * Returns the CSRF Token Name.
     */
    public function get_token_name(): string
    {
        return $this->config->token_name;
    }
    /**
     * Returns the CSRF Header Name.
     */
    public function get_header_name(): string
    {
        return $this->config->header_name;
    }
    /**
     * Returns the CSRF Cookie Name.
     */
    public function get_cookie_name(): string
    {
        return $this->config->cookie_name;
    }
    /**
     * Check if request should be redirect on failure.
     */
    public function should_redirect(): bool
    {
        return $this->config->redirect;
    }
    /**
     * Sanitize Filename
     *
     * Tries to sanitize filenames in order to prevent directory traversal attempts
     * and other security threats, which is particularly useful for files that
     * were supplied via user input.
     *
     * If it is acceptable for the user input to include relative paths,
     * e.g. file/in/some/approved/folder.txt, you can set the second optional
     * parameter, $relativePath to TRUE.
     *
     * @deprecated 4.6.2 Use `sanitize_filename()` instead
     *
     * @param string $str          Input file name
     * @param bool   $relativePath Whether to preserve paths
     */
    public function sanitize_filename(string $str, bool $relative_path = false): string
    {
        helper('security');
        return sanitize_filename($str, $relative_path);
    }
    /**
     * Restore hash from Session or Cookie
     */
    private function restore_hash(): void
    {
        if ($this->is_csrf_cookie()) {
            if ($this->is_hash_in_cookie()) {
                $this->hash = $this->hash_in_cookie;
            }
        } elseif ($this->session->has($this->config->token_name)) {
            // Session based CSRF protection
            $this->hash = $this->session->get($this->config->token_name);
        }
    }
    /**
     * Generates (Regenerates) the CSRF Hash.
     */
    public function generate_hash(): string
    {
        $this->hash = bin2hex(random_bytes(static::CSRF_HASH_BYTES));
        if ($this->is_csrf_cookie()) {
            $this->save_hash_in_cookie();
        } else {
            // Session based CSRF protection
            $this->save_hash_in_session();
        }
        return $this->hash;
    }
    private function is_hash_in_cookie(): bool
    {
        if ($this->hash_in_cookie === null) {
            return false;
        }
        $length = static::CSRF_HASH_BYTES * 2;
        $pattern = '#^[0-9a-f]{' . $length . '}$#iS';
        return preg_match($pattern, $this->hash_in_cookie) === 1;
    }
    private function save_hash_in_cookie(): void
    {
        $this->cookie = new Cookie($this->raw_cookie_name, $this->hash, ['expires' => $this->config->expires === 0 ? 0 : Time::now()->get_timestamp() + $this->config->expires]);
        $response = service('response');
        $response->set_cookie($this->cookie);
    }
    private function save_hash_in_session(): void
    {
        $this->session->set($this->config->token_name, $this->hash);
    }
}