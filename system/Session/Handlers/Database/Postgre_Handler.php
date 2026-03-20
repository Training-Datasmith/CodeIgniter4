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
namespace Code_Igniter\Session\Handlers\Database;

use Code_Igniter\Database\Base_Builder;
use Code_Igniter\Session\Handlers\Database_Handler;
/**
 * Session handler for Postgre
 *
 * @see \CodeIgniter\Session\Handlers\Database\PostgreHandlerTest
 */
class Postgre_Handler extends Database_Handler
{
    /**
     * Sets SELECT clause
     *
     * @return void
     */
    protected function set_select(Base_Builder $builder)
    {
        $builder->select("encode(data, 'base64') AS data");
    }
    /**
     * Decodes column data
     *
     * @param string $data
     *
     * @return false|string
     */
    protected function decode_data($data)
    {
        return base64_decode(rtrim($data), true);
    }
    /**
     * Prepare data to insert/update
     */
    protected function prepare_data(string $data): string
    {
        return '\x' . bin2hex($data);
    }
    /**
     * Cleans up expired sessions.
     *
     * @param int $max_lifetime Sessions that have not updated
     *                          for the last max_lifetime seconds will be removed.
     */
    public function gc($max_lifetime): false|int
    {
        $separator = '\'';
        $interval = implode($separator, ['', "{$max_lifetime} second", '']);
        return $this->db->table($this->table)->where('timestamp <', "now() - INTERVAL {$interval}", false)->delete() ? 1 : $this->fail();
    }
    /**
     * Lock the session.
     */
    protected function lock_session(string $session_id): bool
    {
        $arg = "hashtext('{$session_id}')" . ($this->match_ip ? ", hashtext('{$this->ip_address}')" : '');
        if ($this->db->simple_query("SELECT pg_advisory_lock({$arg})") !== false) {
            $this->lock = $arg;
            return true;
        }
        return $this->fail();
    }
    /**
     * Releases the lock, if any.
     */
    protected function release_lock(): bool
    {
        if (!$this->lock) {
            return true;
        }
        if ($this->db->simple_query("SELECT pg_advisory_unlock({$this->lock})") !== false) {
            $this->lock = false;
            return true;
        }
        return $this->fail();
    }
}