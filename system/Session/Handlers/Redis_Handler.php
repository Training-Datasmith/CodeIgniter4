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
use Redis;
use Redis_Exception;
/**
 * Session handler using Redis for persistence.
 */
class Redis_Handler extends Base_Handler
{
    use Persists_Connection;
    private const DEFAULT_PORT = 6379;
    private const DEFAULT_PROTOCOL = 'tcp';
    /**
     * phpRedis instance.
     *
     * @var Redis|null
     */
    protected $redis;
    /**
     * Key prefix.
     *
     * @var string
     */
    protected $key_prefix = 'ci_session:';
    /**
     * Lock key.
     *
     * @var string|null
     */
    protected $lock_key;
    /**
     * Key exists flag.
     *
     * @var bool
     */
    protected $key_exists = false;
    /**
     * Number of seconds until the session ends.
     *
     * @var int
     */
    protected $session_expiration = 7200;
    /**
     * Time (microseconds) to wait if lock cannot be acquired.
     */
    private int $lock_retry_interval = 100000;
    /**
     * Maximum number of lock acquisition attempts.
     */
    private int $lock_max_retries = 300;
    /**
     * @param string $ipAddress User's IP address.
     *
     * @throws SessionException
     */
    public function __construct(Session_Config $config, string $ip_address)
    {
        parent::__construct($config, $ip_address);
        // Store Session configurations.
        $this->session_expiration = $config->expiration === 0 ? (int) ini_get('session.gc_maxlifetime') : $config->expiration;
        // Add session cookie name for multiple session cookies.
        $this->key_prefix .= $config->cookie_name . ':';
        $this->set_save_path();
        if ($this->match_ip === true) {
            $this->key_prefix .= $this->ip_address . ':';
        }
        $this->lock_retry_interval = $config->lock_wait ?? $this->lock_retry_interval;
        $this->lock_max_retries = $config->lock_attempts ?? $this->lock_max_retries;
    }
    protected function set_save_path(): void
    {
        if ($this->save_path === '') {
            throw Session_Exception::for_empty_savepath();
        }
        $url = parse_url($this->save_path);
        $query = [];
        if ($url === false) {
            // Unix domain socket like `unix:///var/run/redis/redis.sock?persistent=1`.
            if (preg_match('#unix://(/[^:?]+)(\?.+)?#', $this->save_path, $matches)) {
                $host = $matches[1];
                $port = 0;
                if (isset($matches[2])) {
                    parse_str(ltrim($matches[2], '?'), $query);
                }
            } else {
                throw Session_Exception::for_invalid_save_path_format($this->save_path);
            }
        } else {
            // Also accepts `/var/run/redis.sock` for backward compatibility.
            if (isset($url['path']) && $url['path'][0] === '/') {
                $host = $url['path'];
                $port = 0;
            } else {
                // TCP connection.
                if (!isset($url['host'])) {
                    throw Session_Exception::for_invalid_save_path_format($this->save_path);
                }
                $protocol = $url['scheme'] ?? self::DEFAULT_PROTOCOL;
                $host = $protocol . '://' . $url['host'];
                $port = $url['port'] ?? self::DEFAULT_PORT;
            }
            if (isset($url['query'])) {
                parse_str($url['query'], $query);
            }
        }
        $persistent = isset($query['persistent']) ? filter_var($query['persistent'], FILTER_VALIDATE_BOOL) : null;
        $password = $query['auth'] ?? null;
        $database = isset($query['database']) ? (int) $query['database'] : 0;
        $timeout = isset($query['timeout']) ? (float) $query['timeout'] : 0.0;
        $prefix = $query['prefix'] ?? null;
        $this->save_path = ['host' => $host, 'port' => $port, 'password' => $password, 'database' => $database, 'timeout' => $timeout, 'persistent' => $persistent];
        if ($prefix !== null) {
            $this->key_prefix = $prefix;
        }
    }
    /**
     * Re-initialize existing session, or creates a new one.
     *
     * @param string $path The path where to store/retrieve the session.
     * @param string $name The session name.
     *
     * @throws RedisException
     */
    public function open($path, $name): bool
    {
        if (empty($this->save_path)) {
            return false;
        }
        if ($this->has_persistent_connection()) {
            $redis = $this->get_persistent_connection();
            try {
                $ping_reply = $redis->ping();
                if (in_array($ping_reply, [true, '+PONG'], true)) {
                    $this->redis = $redis;
                    return true;
                }
            } catch (Redis_Exception) {
                $this->set_persistent_connection(null);
            }
        }
        $redis = new Redis();
        $func_connection = isset($this->save_path['persistent']) && $this->save_path['persistent'] === true ? 'pconnect' : 'connect';
        if ($redis->{$func_connection}($this->save_path['host'], $this->save_path['port'], $this->save_path['timeout']) === false) {
            $this->logger->error('Session: Unable to connect to Redis with the configured settings.');
        } elseif (isset($this->save_path['password']) && !$redis->auth($this->save_path['password'])) {
            $this->logger->error('Session: Unable to authenticate to Redis instance.');
        } elseif (isset($this->save_path['database']) && !$redis->select($this->save_path['database'])) {
            $this->logger->error('Session: Unable to select Redis database with index ' . $this->save_path['database']);
        } else {
            $this->set_persistent_connection($redis);
            $this->redis = $redis;
            return true;
        }
        return false;
    }
    /**
     * Reads the session data from the session storage, and returns the results.
     *
     * @param string $id The session ID.
     *
     * @throws RedisException
     */
    public function read($id): false|string
    {
        if (isset($this->redis) && $this->lock_session($id)) {
            if (!isset($this->session_id)) {
                $this->session_id = $id;
            }
            $data = $this->redis->get($this->key_prefix . $id);
            if (is_string($data)) {
                $this->key_exists = true;
            } else {
                $data = '';
            }
            $this->fingerprint = md5($data);
            return $data;
        }
        return false;
    }
    /**
     * Writes the session data to the session storage.
     *
     * @param string $id   The session ID.
     * @param string $data The encoded session data.
     *
     * @throws RedisException
     */
    public function write($id, $data): bool
    {
        if (!isset($this->redis)) {
            return false;
        }
        if ($this->session_id !== $id) {
            if (!$this->release_lock() || !$this->lock_session($id)) {
                return false;
            }
            $this->key_exists = false;
            $this->session_id = $id;
        }
        if (isset($this->lock_key)) {
            $this->redis->expire($this->lock_key, 300);
            if ($this->fingerprint !== ($fingerprint = md5($data)) || $this->key_exists === false) {
                if ($this->redis->set($this->key_prefix . $id, $data, $this->session_expiration)) {
                    $this->fingerprint = $fingerprint;
                    $this->key_exists = true;
                    return true;
                }
                return false;
            }
            return $this->redis->expire($this->key_prefix . $id, $this->session_expiration);
        }
        return false;
    }
    /**
     * Closes the current session.
     */
    public function close(): bool
    {
        if (isset($this->redis)) {
            try {
                $ping_reply = $this->redis->ping();
                if (in_array($ping_reply, [true, '+PONG'], true) && isset($this->lock_key) && !$this->release_lock()) {
                    return false;
                }
            } catch (Redis_Exception $e) {
                $this->logger->error('Session: Got RedisException on close(): ' . $e->get_message());
            }
            return true;
        }
        return true;
    }
    /**
     * Destroys a session.
     *
     * @param string $id The session ID being destroyed.
     *
     * @throws RedisException
     */
    public function destroy($id): bool
    {
        if (isset($this->redis, $this->lock_key)) {
            if (($result = $this->redis->del($this->key_prefix . $id)) !== 1) {
                $this->logger->debug('Session: Redis::del() expected to return 1, got ' . var_export($result, true) . ' instead.');
            }
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
     *
     * @throws RedisException
     */
    protected function lock_session(string $session_id): bool
    {
        $lock_key = $this->key_prefix . $session_id . ':lock';
        // PHP 7 reuses the SessionHandler object on regeneration,
        // so we need to check here if the lock key is for the
        // correct session ID.
        if ($this->lock_key === $lock_key) {
            // If there is the lock, make the ttl longer.
            return $this->redis->expire($this->lock_key, 300);
        }
        $attempt = 0;
        do {
            $result = $this->redis->set(
                $lock_key,
                (string) Time::now()->get_timestamp(),
                // NX -- Only set the key if it does not already exist.
                // EX seconds -- Set the specified expire time, in seconds.
                ['nx', 'ex' => 300]
            );
            if (!$result) {
                usleep($this->lock_retry_interval);
                continue;
            }
            $this->lock_key = $lock_key;
            break;
        } while (++$attempt < $this->lock_max_retries);
        if ($attempt === 300) {
            $this->logger->error('Session: Unable to obtain lock for ' . $this->key_prefix . $session_id . ' after 300 attempts, aborting.');
            return false;
        }
        $this->lock = true;
        return true;
    }
    /**
     * Releases a previously acquired lock.
     *
     * @throws RedisException
     */
    protected function release_lock(): bool
    {
        if (isset($this->redis, $this->lock_key) && $this->lock) {
            if (!$this->redis->del($this->lock_key)) {
                $this->logger->error('Session: Error while trying to free lock for ' . $this->lock_key);
                return false;
            }
            $this->lock_key = null;
            $this->lock = false;
        }
        return true;
    }
}