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

use Code_Igniter\Session\Handlers\Database_Handler;
/**
 * Session handler for MySQLi
 *
 * @see \CodeIgniter\Session\Handlers\Database\MySQLiHandlerTest
 */
class My_Sq_Li_Handler extends Database_Handler
{
    /**
     * Lock the session.
     */
    protected function lock_session(string $session_id): bool
    {
        $arg = md5($session_id . ($this->match_ip ? '_' . $this->ip_address : ''));
        if ($this->db->query("SELECT GET_LOCK('{$arg}', 300) AS ci_session_lock")->get_row()->ci_session_lock) {
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
        if ($this->db->query("SELECT RELEASE_LOCK('{$this->lock}') AS ci_session_lock")->get_row()->ci_session_lock) {
            $this->lock = false;
            return true;
        }
        return $this->fail();
    }
}