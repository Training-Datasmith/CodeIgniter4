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
namespace Code_Igniter\Database\Postgre;

use Code_Igniter\Database\Base_Utils;
use Code_Igniter\Database\Exceptions\Database_Exception;
/**
 * Utils for Postgre
 */
class Utils extends Base_Utils
{
    /**
     * List databases statement
     *
     * @var string
     */
    protected $list_databases = 'SELECT datname FROM pg_database';
    /**
     * OPTIMIZE TABLE statement
     *
     * @var string
     */
    protected $optimize_table = 'REINDEX TABLE %s';
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