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
namespace Code_Igniter\Session\Handlers;

use Config\Cookie as CookieConfig;
use Config\Session as SessionConfig;
use Psr\Log\Logger_Aware_Trait;
use Session_Handler_Interface;
/**
 * Base class for session handling.
 */
abstract class Base_Handler implements Session_Handler_Interface
{
    use Logger_Aware_Trait;
    /**
     * The Data fingerprint.
     *
     * @var string
     */
    protected $fingerprint;
    /**
     * Lock placeholder.
     *
     * @var bool|string
     */
    protected $lock = false;
    /**
     * Cookie prefix.
     *
     * The Config\Cookie::$prefix setting is completely ignored.
     * See https://codeigniter.com/user_guide/libraries/sessions.html#session-preferences
     *
     * @var string
     */
    protected $cookie_prefix = '';
    /**
     * Cookie domain.
     *
     * @var string
     */
    protected $cookie_domain = '';
    /**
     * Cookie path.
     *
     * @var string
     */
    protected $cookie_path = '/';
    /**
     * Cookie secure?
     *
     * @var bool
     */
    protected $cookie_secure = false;
    /**
     * Cookie name to use.
     *
     * @var string
     */
    protected $cookie_name;
    /**
     * Match IP addresses for cookies?
     *
     * @var bool
     */
    protected $match_ip = false;
    /**
     * Current session ID.
     *
     * @var string|null
     */
    protected $session_id;
    /**
     * The 'save path' for the session
     * varies between.
     *
     * @var array<string, mixed>|string
     */
    protected $save_path;
    /**
     * User's IP address.
     *
     * @var string
     */
    protected $ip_address;
    public function __construct(Session_Config $config, string $ip_address)
    {
        // Store Session configurations
        $this->cookie_name = $config->cookie_name;
        $this->match_ip = $config->match_ip;
        $this->save_path = $config->save_path;
        $cookie = config(Cookie_Config::class);
        // Session cookies have no prefix.
        $this->cookie_domain = $cookie->domain;
        $this->cookie_path = $cookie->path;
        $this->cookie_secure = $cookie->secure;
        $this->ip_address = $ip_address;
    }
    /**
     * Internal method to force removal of a cookie by the client
     * when session_destroy() is called.
     */
    protected function destroy_cookie(): bool
    {
        return setcookie($this->cookie_name, '', ['expires' => 1, 'path' => $this->cookie_path, 'domain' => $this->cookie_domain, 'secure' => $this->cookie_secure, 'httponly' => true]);
    }
    /**
     * A dummy method allowing drivers with no locking functionality
     * (databases other than PostgreSQL and MySQL) to act as if they
     * do acquire a lock.
     */
    protected function lock_session(string $session_id): bool
    {
        $this->lock = true;
        return true;
    }
    /**
     * Releases the lock, if any.
     */
    protected function release_lock(): bool
    {
        $this->lock = false;
        return true;
    }
    /**
     * Drivers other than the 'files' one don't (need to) use the
     * session.save_path INI setting, but that leads to confusing
     * error messages emitted by PHP when open() or write() fail,
     * as the message contains session.save_path ...
     *
     * To work around the problem, the drivers will call this method
     * so that the INI is set just in time for the error message to
     * be properly generated.
     */
    protected function fail(): bool
    {
        ini_set('session.save_path', $this->save_path);
        return false;
    }
}