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

use Code_Igniter\I18n\Time;
use Code_Igniter\Session\Exceptions\Session_Exception;
use Code_Igniter\Session\Persists_Connection;
use Config\Session as SessionConfig;
use Memcached;
/**
 * Session handler using Memcached for persistence.
 */
class Memcached_Handler extends Base_Handler
{
    use Persists_Connection;
    /**
     * Memcached instance.
     *
     * @var Memcached|null
     */
    protected $memcached;
    /**
     * Key prefix.
     *
     * @var string
     */
    protected $key_prefix = 'ci_session:';
    /**
     * Lock key
     *
     * @var string|null
     */
    protected $lock_key;
    /**
     * Number of seconds until the session ends.
     *
     * @var int
     */
    protected $session_expiration = 7200;
    /**
     * @throws SessionException
     */
    public function __construct(Session_Config $config, string $ip_address)
    {
        parent::__construct($config, $ip_address);
        $this->session_expiration = $config->expiration;
        if ($this->save_path === '') {
            throw Session_Exception::for_empty_savepath();
        }
        // Add session cookie name for multiple session cookies.
        $this->key_prefix .= $config->cookie_name . ':';
        if ($this->match_ip === true) {
            $this->key_prefix .= $this->ip_address . ':';
        }
        ini_set('memcached.sess_prefix', $this->key_prefix);
    }
    /**
     * Re-initialize existing session, or creates a new one.
     *
     * @param string $path The path where to store/retrieve the session.
     * @param string $name The session name.
     */
    public function open($path, $name): bool
    {
        if ($this->has_persistent_connection()) {
            $memcached = $this->get_persistent_connection();
            $version = $memcached->get_version();
            if (is_array($version)) {
                foreach ($version as $server_version) {
                    if ($server_version !== false) {
                        $this->memcached = $memcached;
                        return true;
                    }
                }
            }
            $this->set_persistent_connection(null);
        }
        $this->memcached = new Memcached();
        $this->memcached->set_option(Memcached::OPT_BINARY_PROTOCOL, true);
        // required for touch() usage
        $server_list = [];
        foreach ($this->memcached->get_server_list() as $server) {
            $server_list[] = $server['host'] . ':' . $server['port'];
        }
        if (preg_match_all('#,?([^,:]+)\:(\d{1,5})(?:\:(\d+))?#', $this->save_path, $matches, PREG_SET_ORDER) < 1) {
            $this->memcached = null;
            $this->logger->error('Session: Invalid Memcached save path format: ' . $this->save_path);
            return false;
        }
        foreach ($matches as $match) {
            // If Memcached already has this server (or if the port is invalid), skip it
            if (in_array($match[1] . ':' . $match[2], $server_list, true)) {
                $this->logger->debug('Session: Memcached server pool already has ' . $match[1] . ':' . $match[2]);
                continue;
            }
            if (!$this->memcached->add_server($match[1], (int) $match[2], $match[3] ?? 0)) {
                $this->logger->error('Could not add ' . $match[1] . ':' . $match[2] . ' to Memcached server pool.');
            } else {
                $server_list[] = $match[1] . ':' . $match[2];
            }
        }
        if ($server_list === []) {
            $this->logger->error('Session: Memcached server pool is empty.');
            return false;
        }
        $this->set_persistent_connection($this->memcached);
        return true;
    }
    /**
     * Reads the session data from the session storage, and returns the results.
     *
     * @param string $id The session ID.
     */
    public function read($id): false|string
    {
        if (isset($this->memcached) && $this->lock_session($id)) {
            if (!isset($this->session_id)) {
                $this->session_id = $id;
            }
            $data = (string) $this->memcached->get($this->key_prefix . $id);
            $this->fingerprint = md5($data);
            return $data;
        }
        return '';
    }
    /**
     * Writes the session data to the session storage.
     *
     * @param string $id   The session ID.
     * @param string $data The encoded session data.
     */
    public function write($id, $data): bool
    {
        if (!isset($this->memcached)) {
            return false;
        }
        if ($this->session_id !== $id) {
            if (!$this->release_lock() || !$this->lock_session($id)) {
                return false;
            }
            $this->fingerprint = md5('');
            $this->session_id = $id;
        }
        if (isset($this->lock_key)) {
            $this->memcached->replace($this->lock_key, Time::now()->get_timestamp(), 300);
            if ($this->fingerprint !== $fingerprint = md5($data)) {
                if ($this->memcached->set($this->key_prefix . $id, $data, $this->session_expiration)) {
                    $this->fingerprint = $fingerprint;
                    return true;
                }
                return false;
            }
            return $this->memcached->touch($this->key_prefix . $id, $this->session_expiration);
        }
        return false;
    }
    /**
     * Closes the current session.
     */
    public function close(): bool
    {
        if (isset($this->memcached)) {
            if (isset($this->lock_key)) {
                $this->memcached->delete($this->lock_key);
            }
            return true;
        }
        return false;
    }
    /**
     * Destroys a session.
     *
     * @param string $id The session ID being destroyed.
     */
    public function destroy($id): bool
    {
        if (isset($this->memcached, $this->lock_key)) {
            $this->memcached->delete($this->key_prefix . $id);
            return $this->destroy_cookie();
        }
        return false;
    }
    /**
     * Cleans up expired sessions.
     *
     * @param int $max_lifetime Sessions that have not updated
     *                          for the last max_lifetime seconds will be removed.
     */
    public function gc($max_lifetime): int
    {
        return 1;
    }
    /**
     * Acquires an emulated lock.
     *
     * @param string $sessionID Session ID.
     */
    protected function lock_session(string $session_id): bool
    {
        if (isset($this->lock_key)) {
            return $this->memcached->replace($this->lock_key, Time::now()->get_timestamp(), 300);
        }
        $lock_key = $this->key_prefix . $session_id . ':lock';
        $attempt = 0;
        do {
            if ($this->memcached->get($lock_key) !== false) {
                sleep(1);
                continue;
            }
            if (!$this->memcached->set($lock_key, Time::now()->get_timestamp(), 300)) {
                $this->logger->error('Session: Error while trying to obtain lock for ' . $this->key_prefix . $session_id);
                return false;
            }
            $this->lock_key = $lock_key;
            break;
        } while (++$attempt < 30);
        if ($attempt === 30) {
            $this->logger->error('Session: Unable to obtain lock for ' . $this->key_prefix . $session_id . ' after 30 attempts, aborting.');
            return false;
        }
        $this->lock = true;
        return true;
    }
    /**
     * Releases a previously acquired lock.
     */
    protected function release_lock(): bool
    {
        if (isset($this->memcached, $this->lock_key) && $this->lock) {
            if (!$this->memcached->delete($this->lock_key) && $this->memcached->get_result_code() !== Memcached::RES_NOTFOUND) {
                $this->logger->error('Session: Error while trying to free lock for ' . $this->lock_key);
                return false;
            }
            $this->lock_key = null;
            $this->lock = false;
        }
        return true;
    }
}