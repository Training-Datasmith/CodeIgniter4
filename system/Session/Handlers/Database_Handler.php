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

use Code_Igniter\Database\Base_Builder;
use Code_Igniter\Database\Base_Connection;
use Code_Igniter\Session\Exceptions\Session_Exception;
use Config\Database;
use Config\Session as SessionConfig;
/**
 * Base database session handler.
 *
 * Do not use this class. Use database specific handler class.
 */
class Database_Handler extends Base_Handler
{
    /**
     * The database group to use for storage.
     *
     * @var string
     */
    protected $db_group;
    /**
     * The name of the table to store session info.
     *
     * @var string
     */
    protected $table;
    /**
     * The DB Connection instance.
     *
     * @var BaseConnection
     */
    protected $db;
    /**
     * The database type.
     *
     * @var string
     */
    protected $platform;
    /**
     * Row exists flag.
     *
     * @var bool
     */
    protected $row_exists = false;
    /**
     * ID prefix for multiple session cookies.
     */
    protected string $id_prefix;
    /**
     * @throws SessionException
     */
    public function __construct(Session_Config $config, string $ip_address)
    {
        parent::__construct($config, $ip_address);
        $this->table = $this->save_path;
        if ($this->table === '') {
            throw Session_Exception::for_missing_database_table();
        }
        // Store Session configurations
        $this->db_group = $config->db_group ?? config(Database::class)->default_group;
        // Add session cookie name for multiple session cookies.
        $this->id_prefix = $config->cookie_name . ':';
        $this->db = Database::connect($this->db_group);
        $this->platform = $this->db->get_platform();
    }
    /**
     * Re-initialize existing session, or creates a new one.
     *
     * @param string $path The path where to store/retrieve the session
     * @param string $name The session name
     */
    public function open($path, $name): bool
    {
        if ($this->db->conn_id === false) {
            $this->db->initialize();
        }
        return true;
    }
    /**
     * Reads the session data from the session storage, and returns the results.
     *
     * @param string $id The session ID
     */
    public function read($id): false|string
    {
        if ($this->lock_session($id) === false) {
            $this->fingerprint = md5('');
            return '';
        }
        if (!isset($this->session_id)) {
            $this->session_id = $id;
        }
        $builder = $this->db->table($this->table)->where('id', $this->id_prefix . $id);
        if ($this->match_ip) {
            $builder = $builder->where('ip_address', $this->ip_address);
        }
        $this->set_select($builder);
        $result = $builder->get()->get_row();
        if ($result === null) {
            // PHP7 will reuse the same SessionHandler object after
            // ID regeneration, so we need to explicitly set this to
            // FALSE instead of relying on the default ...
            $this->row_exists = false;
            $this->fingerprint = md5('');
            return '';
        }
        $result = is_bool($result) ? '' : $this->decode_data($result->data);
        $this->fingerprint = md5($result);
        $this->row_exists = true;
        return $result;
    }
    /**
     * Sets SELECT clause.
     *
     * @return void
     */
    protected function set_select(Base_Builder $builder)
    {
        $builder->select('data');
    }
    /**
     * Decodes column data.
     *
     * @param string $data
     *
     * @return false|string
     */
    protected function decode_data($data)
    {
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
        if ($this->lock === false) {
            return $this->fail();
        }
        if ($this->session_id !== $id) {
            $this->row_exists = false;
            $this->session_id = $id;
        }
        if ($this->row_exists === false) {
            $insert_data = ['id' => $this->id_prefix . $id, 'ip_address' => $this->ip_address, 'data' => $this->prepare_data($data)];
            if (!$this->db->table($this->table)->set('timestamp', 'now()', false)->insert($insert_data)) {
                return $this->fail();
            }
            $this->fingerprint = md5($data);
            $this->row_exists = true;
            return true;
        }
        $builder = $this->db->table($this->table)->where('id', $this->id_prefix . $id);
        if ($this->match_ip) {
            $builder = $builder->where('ip_address', $this->ip_address);
        }
        $update_data = [];
        if ($this->fingerprint !== md5($data)) {
            $update_data['data'] = $this->prepare_data($data);
        }
        if (!$builder->set('timestamp', 'now()', false)->update($update_data)) {
            return $this->fail();
        }
        $this->fingerprint = md5($data);
        return true;
    }
    /**
     * Prepare data to insert/update.
     */
    protected function prepare_data(string $data): string
    {
        return $data;
    }
    /**
     * Closes the current session.
     */
    public function close(): bool
    {
        return $this->lock && !$this->release_lock() ? $this->fail() : true;
    }
    /**
     * Destroys a session.
     *
     * @param string $id The session ID being destroyed.
     */
    public function destroy($id): bool
    {
        if ($this->lock) {
            $builder = $this->db->table($this->table)->where('id', $this->id_prefix . $id);
            if ($this->match_ip) {
                $builder = $builder->where('ip_address', $this->ip_address);
            }
            if (!$builder->delete()) {
                return $this->fail();
            }
        }
        if ($this->close()) {
            $this->destroy_cookie();
            return true;
        }
        return $this->fail();
    }
    /**
     * Cleans up expired sessions.
     *
     * @param int $max_lifetime Sessions that have not updated
     *                          for the last max_lifetime seconds will be removed.
     *
     * @return false|int Returns the number of deleted sessions on success, or false on failure.
     */
    public function gc($max_lifetime): false|int
    {
        return $this->db->table($this->table)->where('timestamp <', "now() - INTERVAL {$max_lifetime} second", false)->delete() ? 1 : $this->fail();
    }
    /**
     * Releases the lock, if any.
     */
    protected function release_lock(): bool
    {
        if (!$this->lock) {
            return true;
        }
        // Unsupported DB? Let the parent handle the simple version.
        return parent::release_lock();
    }
}