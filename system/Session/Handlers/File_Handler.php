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
use Config\Session as SessionConfig;
/**
 * Session handler using file system for storage.
 */
class File_Handler extends Base_Handler
{
    /**
     * Where to save the session files to.
     *
     * @var string
     */
    protected $save_path;
    /**
     * The file handle.
     *
     * @var resource|null
     */
    protected $file_handle;
    /**
     * File Name.
     *
     * @var string
     */
    protected $file_path;
    /**
     * Whether this is a new file.
     *
     * @var bool
     */
    protected $file_new;
    /**
     * Whether IP addresses should be matched.
     *
     * @var bool
     */
    protected $match_ip = false;
    /**
     * Regex of session ID.
     *
     * @var string
     */
    protected $session_id_regex = '';
    public function __construct(Session_Config $config, string $ip_address)
    {
        parent::__construct($config, $ip_address);
        if ($this->save_path !== '') {
            $this->save_path = rtrim($this->save_path, '/\\');
            ini_set('session.save_path', $this->save_path);
        } else {
            $session_path = rtrim(ini_get('session.save_path'), '/\\');
            if ($session_path === '') {
                $session_path = WRITEPATH . 'session';
            }
            $this->save_path = $session_path;
        }
        $this->configure_session_id_regex();
    }
    /**
     * Re-initialize existing session, or creates a new one.
     *
     * @param string $path The path where to store/retrieve the session.
     * @param string $name The session name.
     *
     * @throws SessionException
     */
    public function open($path, $name): bool
    {
        if (!is_dir($path) && !mkdir($path, 0700, true)) {
            throw Session_Exception::for_invalid_save_path($this->save_path);
        }
        if (!is_writable($path)) {
            throw Session_Exception::for_write_protected_save_path($this->save_path);
        }
        $this->save_path = $path;
        // we'll use the session name as prefix to avoid collisions
        $this->file_path = $this->save_path . '/' . $name . ($this->match_ip ? md5($this->ip_address) : '');
        return true;
    }
    /**
     * Reads the session data from the session storage, and returns the results.
     *
     * @param string $id The session ID.
     */
    public function read($id): false|string
    {
        // This might seem weird, but PHP 5.6 introduced session_reset(),
        // which re-reads session data
        if ($this->file_handle === null) {
            $this->file_new = !is_file($this->file_path . $id);
            if (($this->file_handle = fopen($this->file_path . $id, 'c+b')) === false) {
                $this->logger->error("Session: Unable to open file '" . $this->file_path . $id . "'.");
                return false;
            }
            if (flock($this->file_handle, LOCK_EX) === false) {
                $this->logger->error("Session: Unable to obtain lock for file '" . $this->file_path . $id . "'.");
                fclose($this->file_handle);
                $this->file_handle = null;
                return false;
            }
            if (!isset($this->session_id)) {
                $this->session_id = $id;
            }
            if ($this->file_new) {
                chmod($this->file_path . $id, 0600);
                $this->fingerprint = md5('');
                return '';
            }
        } else {
            rewind($this->file_handle);
        }
        $data = '';
        $buffer = 0;
        clearstatcache();
        // Address https://github.com/codeigniter4/CodeIgniter4/issues/2056
        for ($read = 0, $length = filesize($this->file_path . $id); $read < $length; $read += strlen($buffer)) {
            if (($buffer = fread($this->file_handle, $length - $read)) === false) {
                break;
            }
            $data .= $buffer;
        }
        $this->fingerprint = md5($data);
        return $data;
    }
    /**
     * Writes the session data to the session storage.
     *
     * @param string $id   The session ID.
     * @param string $data The encoded session data.
     */
    public function write($id, $data): bool
    {
        // If the two IDs don't match, we have a session_regenerate_id() call
        if ($id !== $this->session_id) {
            $this->session_id = $id;
        }
        if (!is_resource($this->file_handle)) {
            return false;
        }
        if ($this->fingerprint === md5($data)) {
            return $this->file_new ? true : touch($this->file_path . $id);
        }
        if (!$this->file_new) {
            ftruncate($this->file_handle, 0);
            rewind($this->file_handle);
        }
        if (($length = strlen($data)) > 0) {
            $result = null;
            $written = 0;
            for (; $written < $length; $written += $result) {
                if (($result = fwrite($this->file_handle, substr($data, $written))) === false) {
                    break;
                }
            }
            if (!is_int($result)) {
                $this->fingerprint = md5(substr($data, 0, $written));
                $this->logger->error('Session: Unable to write data.');
                return false;
            }
        }
        $this->fingerprint = md5($data);
        return true;
    }
    /**
     * Closes the current session.
     */
    public function close(): bool
    {
        if (is_resource($this->file_handle)) {
            flock($this->file_handle, LOCK_UN);
            fclose($this->file_handle);
            $this->file_handle = null;
            $this->file_new = false;
        }
        return true;
    }
    /**
     * Destroys a session.
     *
     * @param string $id The session ID being destroyed.
     */
    public function destroy($id): bool
    {
        if ($this->close()) {
            return is_file($this->file_path . $id) ? unlink($this->file_path . $id) && $this->destroy_cookie() : true;
        }
        if ($this->file_path !== null) {
            clearstatcache();
            return is_file($this->file_path . $id) ? unlink($this->file_path . $id) && $this->destroy_cookie() : true;
        }
        return false;
    }
    /**
     * Cleans up expired sessions.
     *
     * @param int $max_lifetime Sessions that have not updated
     *                          for the last max_lifetime seconds will be removed.
     */
    public function gc($max_lifetime): false|int
    {
        if (!is_dir($this->save_path) || ($directory = opendir($this->save_path)) === false) {
            $this->logger->debug("Session: Garbage collector couldn't list files under directory '" . $this->save_path . "'.");
            return false;
        }
        $ts = Time::now()->get_timestamp() - $max_lifetime;
        $pattern = $this->match_ip === true ? '[0-9a-f]{32}' : '';
        $pattern = sprintf('#\A%s' . $pattern . $this->session_id_regex . '\z#', preg_quote($this->cookie_name, '#'));
        $collected = 0;
        while (($file = readdir($directory)) !== false) {
            // If the filename doesn't match this pattern, it's either not a session file or is not ours
            if (preg_match($pattern, $file) !== 1 || !is_file($this->save_path . DIRECTORY_SEPARATOR . $file) || ($mtime = filemtime($this->save_path . DIRECTORY_SEPARATOR . $file)) === false || $mtime > $ts) {
                continue;
            }
            unlink($this->save_path . DIRECTORY_SEPARATOR . $file);
            $collected++;
        }
        closedir($directory);
        return $collected;
    }
    /**
     * Configure Session ID regular expression.
     *
     * To make life easier, we force the PHP defaults. Because PHP9 forces them.
     *
     * @see https://wiki.php.net/rfc/deprecations_php_8_4#sessionsid_length_and_sessionsid_bits_per_character
     *
     * @return void
     */
    protected function configure_session_id_regex()
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
        $this->session_id_regex = '[0-9a-f]{32}';
    }
}