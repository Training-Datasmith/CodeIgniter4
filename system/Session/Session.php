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
namespace Code_Igniter\Session;

use Code_Igniter\Cookie\Cookie;
use Code_Igniter\I18n\Time;
use Config\Cookie as CookieConfig;
use Config\Session as SessionConfig;
use Psr\Log\Logger_Aware_Trait;
use Session_Handler_Interface;
/**
 * Implementation of CodeIgniter session container.
 *
 * Session configuration is done through session variables and cookie related
 * variables in `Сonfig\Session`.
 *
 * @property string $session_id
 *
 * @see \CodeIgniter\Session\SessionTest
 */
class Session implements Session_Interface
{
    use Logger_Aware_Trait;
    /**
     * Instance of the driver to use.
     *
     * @var SessionHandlerInterface
     */
    protected $driver;
    /**
     * The session cookie instance.
     *
     * @var Cookie
     */
    protected $cookie;
    /**
     * Session ID regex expression.
     *
     * @var string
     */
    protected $sid_regexp;
    protected Session_Config $config;
    /**
     * Extract configuration settings and save them here.
     */
    public function __construct(Session_Handler_Interface $driver, Session_Config $config)
    {
        $this->driver = $driver;
        $this->config = $config;
        $cookie = config(Cookie_Config::class);
        $this->cookie = (new Cookie($this->config->cookie_name, '', [
            'expires' => $this->config->expiration === 0 ? 0 : Time::now()->get_timestamp() + $this->config->expiration,
            'path' => $cookie->path,
            'domain' => $cookie->domain,
            'secure' => $cookie->secure,
            'httponly' => true,
            // for security
            'samesite' => $cookie->samesite ?? Cookie::SAMESITE_LAX,
            'raw' => $cookie->raw ?? false,
        ]))->with_prefix('');
        // Cookie prefix should be ignored.
        helper('array');
    }
    /**
     * Initialize the session container and starts up the session.
     *
     * @return $this|null
     */
    public function start()
    {
        if (is_cli() && ENVIRONMENT !== 'testing') {
            // @codeCoverageIgnoreStart
            $this->logger->debug('Session: Initialization under CLI aborted.');
            return null;
            // @codeCoverageIgnoreEnd
        }
        if ((bool) ini_get('session.auto_start')) {
            $this->logger->error('Session: session.auto_start is enabled in php.ini. Aborting.');
            return null;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->logger->warning('Session: Sessions is enabled, and one exists. Please don\'t $session->start();');
            return null;
        }
        $this->configure();
        $this->set_save_handler();
        // Sanitize the cookie, because apparently PHP doesn't do that for userspace handlers
        if (isset($_COOKIE[$this->config->cookie_name]) && (!is_string($_COOKIE[$this->config->cookie_name]) || preg_match('#\A' . $this->sid_regexp . '\z#', $_COOKIE[$this->config->cookie_name]) !== 1)) {
            unset($_COOKIE[$this->config->cookie_name]);
        }
        $this->start_session();
        // Is session ID auto-regeneration configured? (ignoring ajax requests)
        $requested_with = service('superglobals')->server('HTTP_X_REQUESTED_WITH');
        if (($requested_with === null || strtolower($requested_with) !== 'xmlhttprequest') && ($regenerate_time = $this->config->time_to_update) > 0) {
            if (!isset($_SESSION['__ci_last_regenerate'])) {
                $_SESSION['__ci_last_regenerate'] = Time::now()->get_timestamp();
            } elseif ($_SESSION['__ci_last_regenerate'] < Time::now()->get_timestamp() - $regenerate_time) {
                $this->regenerate($this->config->regenerate_destroy);
            }
        } elseif (isset($_COOKIE[$this->config->cookie_name]) && $_COOKIE[$this->config->cookie_name] === session_id()) {
            $this->set_cookie();
        }
        $this->init_vars();
        $this->logger->debug("Session: Class initialized using '" . $this->config->driver . "' driver.");
        return $this;
    }
    /**
     * Configuration.
     *
     * Handle input binds and configuration defaults.
     *
     * @return void
     */
    protected function configure()
    {
        ini_set('session.name', $this->config->cookie_name);
        $same_site = $this->cookie->get_same_site() === '' ? ucfirst(Cookie::SAMESITE_LAX) : $this->cookie->get_same_site();
        $params = [
            'lifetime' => $this->config->expiration,
            'path' => $this->cookie->get_path(),
            'domain' => $this->cookie->get_domain(),
            'secure' => $this->cookie->is_secure(),
            'httponly' => true,
            // HTTP only; Yes, this is intentional and not configurable for security reasons.
            'samesite' => $same_site,
        ];
        ini_set('session.cookie_samesite', $same_site);
        session_set_cookie_params($params);
        if ($this->config->expiration > 0) {
            ini_set('session.gc_maxlifetime', (string) $this->config->expiration);
        }
        if ($this->config->save_path !== '') {
            ini_set('session.save_path', $this->config->save_path);
        }
        // Security is king
        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        $this->configure_sid_length();
    }
    /**
     * Configure session ID length.
     *
     * To make life easier, we force the PHP defaults. Because PHP9 forces them.
     * See https://wiki.php.net/rfc/deprecations_php_8_4#sessionsid_length_and_sessionsid_bits_per_character
     *
     * @return void
     */
    protected function configure_sid_length()
    {
        $bits_per_character = (int) ini_get('session.sid_bits_per_character');
        $sid_length = (int) ini_get('session.sid_length');
        // We force the PHP defaults.
        if (PHP_VERSION_ID < 90000) {
            if ($bits_per_character !== 4) {
                ini_set('session.sid_bits_per_character', '4');
            }
            if ($sid_length !== 32) {
                ini_set('session.sid_length', '32');
            }
        }
        $this->sid_regexp = '[0-9a-f]{32}';
    }
    /**
     * Handle temporary variables.
     *
     * Clears old "flash" data, marks the new one for deletion and handles
     * "temp" data deletion.
     *
     * @return void
     */
    protected function init_vars()
    {
        if (!isset($_SESSION['__ci_vars'])) {
            return;
        }
        $current_time = Time::now()->get_timestamp();
        foreach ($_SESSION['__ci_vars'] as $key => &$value) {
            if ($value === 'new') {
                $_SESSION['__ci_vars'][$key] = 'old';
            } elseif ($value === 'old' || $value < $current_time) {
                unset($_SESSION[$key], $_SESSION['__ci_vars'][$key]);
            }
        }
        if ($_SESSION['__ci_vars'] === []) {
            unset($_SESSION['__ci_vars']);
        }
    }
    public function regenerate(bool $destroy = false)
    {
        $_SESSION['__ci_last_regenerate'] = Time::now()->get_timestamp();
        session_regenerate_id($destroy);
        $this->remove_old_session_cookie();
    }
    private function remove_old_session_cookie(): void
    {
        $response = service('response');
        $cookie_store_in_response = $response->get_cookie_store();
        if (!$cookie_store_in_response->has($this->config->cookie_name)) {
            return;
        }
        // CookieStore is immutable.
        $new_cookie_store = $cookie_store_in_response->remove($this->config->cookie_name);
        // But clear() method clears cookies in the object (not immutable).
        $cookie_store_in_response->clear();
        foreach ($new_cookie_store as $cookie) {
            $response->set_cookie($cookie);
        }
    }
    public function destroy()
    {
        if (ENVIRONMENT === 'testing') {
            return;
        }
        session_destroy();
    }
    /**
     * Writes session data and close the current session.
     *
     * @return void
     */
    public function close()
    {
        if (ENVIRONMENT === 'testing') {
            return;
        }
        session_write_close();
    }
    public function set($data, $value = null)
    {
        $data = is_array($data) ? $data : [$data => $value];
        if (array_is_list($data)) {
            $data = array_fill_keys($data, null);
        }
        foreach ($data as $session_key => $session_value) {
            $_SESSION[$session_key] = $session_value;
        }
    }
    public function get(?string $key = null)
    {
        if (!isset($_SESSION) || $_SESSION === []) {
            return $key === null ? [] : null;
        }
        $key ??= '';
        if ($key !== '') {
            return $_SESSION[$key] ?? dot_array_search($key, $_SESSION);
        }
        $userdata = [];
        $exclude = array_merge(['__ci_vars'], $this->get_flash_keys(), $this->get_temp_keys());
        foreach (array_keys($_SESSION) as $key) {
            if (!in_array($key, $exclude, true)) {
                $userdata[$key] = $_SESSION[$key];
            }
        }
        return $userdata;
    }
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }
    /**
     * Push new value onto session value that is array.
     *
     * @param string               $key  Identifier of the session property we are interested in.
     * @param array<string, mixed> $data value to be pushed to existing session key.
     *
     * @return void
     */
    public function push(string $key, array $data)
    {
        if ($this->has($key) && is_array($value = $this->get($key))) {
            $this->set($key, array_merge($value, $data));
        }
    }
    public function remove($key)
    {
        $key = is_array($key) ? $key : [$key];
        foreach ($key as $k) {
            unset($_SESSION[$k]);
        }
    }
    /**
     * Magic method to set variables in the session by simply calling
     *  $session->foo = 'bar';
     *
     * @param mixed $value
     *
     * @return void
     */
    public function __set(string $key, $value)
    {
        $_SESSION[$key] = $value;
    }
    /**
     * Magic method to get session variables by simply calling
     *  $foo = $session->foo;
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        // Note: Keep this order the same, just in case somebody wants to
        // use 'session_id' as a session data key, for whatever reason
        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
        }
        if ($key === 'session_id') {
            return session_id();
        }
        return null;
    }
    /**
     * Magic method to check for session variables.
     *
     * Different from `has()` in that it will validate 'session_id' as well.
     * Mostly used by internal PHP functions, users should stick to `has()`.
     */
    public function __isset(string $key): bool
    {
        return isset($_SESSION[$key]) || $key === 'session_id';
    }
    public function set_flashdata($data, $value = null)
    {
        $this->set($data, $value);
        $this->mark_as_flashdata(is_array($data) ? array_keys($data) : $data);
    }
    public function get_flashdata(?string $key = null)
    {
        $_SESSION['__ci_vars'] ??= [];
        if (isset($key)) {
            if (!isset($_SESSION['__ci_vars'][$key]) || is_int($_SESSION['__ci_vars'][$key])) {
                return null;
            }
            return $_SESSION[$key] ?? null;
        }
        $flashdata = [];
        foreach ($_SESSION['__ci_vars'] as $key => $value) {
            if (!is_int($value)) {
                $flashdata[$key] = $_SESSION[$key];
            }
        }
        return $flashdata;
    }
    public function keep_flashdata($key)
    {
        $this->mark_as_flashdata($key);
    }
    public function mark_as_flashdata($key): bool
    {
        $keys = is_array($key) ? $key : [$key];
        foreach ($keys as $session_key) {
            if (!isset($_SESSION[$session_key])) {
                return false;
            }
        }
        $_SESSION['__ci_vars'] ??= [];
        $_SESSION['__ci_vars'] = [...$_SESSION['__ci_vars'], ...array_fill_keys($keys, 'new')];
        return true;
    }
    public function unmark_flashdata($key)
    {
        if (!isset($_SESSION['__ci_vars'])) {
            return;
        }
        if (!is_array($key)) {
            $key = [$key];
        }
        foreach ($key as $k) {
            if (isset($_SESSION['__ci_vars'][$k]) && !is_int($_SESSION['__ci_vars'][$k])) {
                unset($_SESSION['__ci_vars'][$k]);
            }
        }
        if ($_SESSION['__ci_vars'] === []) {
            unset($_SESSION['__ci_vars']);
        }
    }
    public function get_flash_keys(): array
    {
        if (!isset($_SESSION['__ci_vars'])) {
            return [];
        }
        $keys = [];
        foreach (array_keys($_SESSION['__ci_vars']) as $key) {
            if (!is_int($_SESSION['__ci_vars'][$key])) {
                $keys[] = $key;
            }
        }
        return $keys;
    }
    public function set_tempdata($data, $value = null, int $ttl = 300)
    {
        $this->set($data, $value);
        $this->mark_as_tempdata($data, $ttl);
    }
    public function get_tempdata(?string $key = null)
    {
        $_SESSION['__ci_vars'] ??= [];
        if (isset($key)) {
            if (!isset($_SESSION['__ci_vars'][$key]) || !is_int($_SESSION['__ci_vars'][$key])) {
                return null;
            }
            return $_SESSION[$key] ?? null;
        }
        $tempdata = [];
        foreach ($_SESSION['__ci_vars'] as $key => $value) {
            if (is_int($value)) {
                $tempdata[$key] = $_SESSION[$key];
            }
        }
        return $tempdata;
    }
    public function remove_tempdata(string $key)
    {
        $this->unmark_tempdata($key);
        unset($_SESSION[$key]);
    }
    public function mark_as_tempdata($key, int $ttl = 300): bool
    {
        $time = Time::now()->get_timestamp();
        $keys = is_array($key) ? $key : [$key];
        if (array_is_list($keys)) {
            $keys = array_fill_keys($keys, $ttl);
        }
        $tempdata = [];
        foreach ($keys as $session_key => $time_to_live) {
            if (!array_key_exists($session_key, $_SESSION)) {
                return false;
            }
            if (is_int($time_to_live)) {
                $time_to_live += $time;
            } else {
                $time_to_live = $time + $ttl;
            }
            $tempdata[$session_key] = $time_to_live;
        }
        $_SESSION['__ci_vars'] ??= [];
        $_SESSION['__ci_vars'] = [...$_SESSION['__ci_vars'], ...$tempdata];
        return true;
    }
    public function unmark_tempdata($key)
    {
        if (!isset($_SESSION['__ci_vars'])) {
            return;
        }
        if (!is_array($key)) {
            $key = [$key];
        }
        foreach ($key as $k) {
            if (isset($_SESSION['__ci_vars'][$k]) && is_int($_SESSION['__ci_vars'][$k])) {
                unset($_SESSION['__ci_vars'][$k]);
            }
        }
        if ($_SESSION['__ci_vars'] === []) {
            unset($_SESSION['__ci_vars']);
        }
    }
    public function get_temp_keys(): array
    {
        if (!isset($_SESSION['__ci_vars'])) {
            return [];
        }
        $keys = [];
        foreach (array_keys($_SESSION['__ci_vars']) as $key) {
            if (is_int($_SESSION['__ci_vars'][$key])) {
                $keys[] = $key;
            }
        }
        return $keys;
    }
    /**
     * Sets the driver as the session handler in PHP.
     * Extracted for easier testing.
     *
     * @return void
     */
    protected function set_save_handler()
    {
        session_set_save_handler($this->driver, true);
    }
    /**
     * Starts the session.
     * Extracted for testing reasons.
     *
     * @return void
     */
    protected function start_session()
    {
        if (ENVIRONMENT === 'testing') {
            $_SESSION = [];
            return;
        }
        session_start();
        // @codeCoverageIgnore
    }
    /**
     * Takes care of setting the cookie on the client side.
     *
     * @codeCoverageIgnore
     *
     * @return void
     */
    protected function set_cookie()
    {
        $expiration = $this->config->expiration === 0 ? 0 : Time::now()->get_timestamp() + $this->config->expiration;
        $this->cookie = $this->cookie->with_value(session_id())->with_expires($expiration);
        $response = service('response');
        $response->set_cookie($this->cookie);
    }
}