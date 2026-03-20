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
namespace Code_Igniter\Database\SQLSRV;

use Code_Igniter\Database\Base_Utils;
use Code_Igniter\Database\Connection_Interface;
use Code_Igniter\Database\Exceptions\Database_Exception;
/**
 * Utils for SQLSRV
 */
class Utils extends Base_Utils
{
    /**
     * List databases statement
     *
     * @var string
     */
    protected $list_databases = 'EXEC sp_helpdb';
    // Can also be: EXEC sp_databases
    /**
     * OPTIMIZE TABLE statement
     *
     * @var string
     */
    protected $optimize_table = 'ALTER INDEX all ON %s REORGANIZE';
    public function __construct(Connection_Interface $db)
    {
        parent::__construct($db);
        $this->optimize_table = 'ALTER INDEX all ON  ' . $this->db->schema . '.%s REORGANIZE';
    }
    /**
     * Platform dependent version of the backup function.
     *
     * @return never
     */
    public function _backup(?array $prefs = null)
    {
        throw new Database_Exception('Unsupported feature of the database platform you are using.');
    }
}