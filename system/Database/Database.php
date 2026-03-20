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
namespace Code_Igniter\Database;

use Code_Igniter\Exceptions\Config_Exception;
use Code_Igniter\Exceptions\Critical_Error;
use Code_Igniter\Exceptions\InvalidArgumentException;
/**
 * Database Connection Factory
 *
 * Creates and returns an instance of the appropriate Database Connection.
 */
class Database
{
    /**
     * Maintains an array of the instances of all connections that have
     * been created.
     *
     * Helps to keep track of all open connections for performance
     * monitoring, logging, etc.
     *
     * @var array
     */
    protected $connections = [];
    /**
     * Parses the connection binds and creates a Database Connection instance.
     *
     * @return BaseConnection
     *
     * @throws InvalidArgumentException
     */
    public function load(array $params = [], string $alias = '')
    {
        if ($alias === '') {
            throw new InvalidArgumentException('You must supply the parameter: alias.');
        }
        if (!empty($params['DSN']) && str_contains($params['DSN'], '://')) {
            $params = $this->parse_dsn($params);
        }
        if (empty($params['DBDriver'])) {
            throw new InvalidArgumentException('You have not selected a database type to connect to.');
        }
        assert($this->check_db_extension($params['DBDriver']));
        $this->connections[$alias] = $this->init_driver($params['DBDriver'], 'Connection', $params);
        return $this->connections[$alias];
    }
    /**
     * Creates a Forge instance for the current database type.
     *
     * @param BaseConnection $db
     */
    public function load_forge(Connection_Interface $db): Forge
    {
        if ($db->conn_id === false) {
            $db->initialize();
        }
        return $this->init_driver($db->db_driver, 'Forge', $db);
    }
    /**
     * Creates an instance of Utils for the current database type.
     *
     * @param BaseConnection $db
     */
    public function load_utils(Connection_Interface $db): Base_Utils
    {
        if ($db->conn_id === false) {
            $db->initialize();
        }
        return $this->init_driver($db->db_driver, 'Utils', $db);
    }
    /**
     * Parses universal DSN string
     *
     * @throws InvalidArgumentException
     */
    protected function parse_dsn(array $params): array
    {
        $dsn = parse_url($params['DSN']);
        if (in_array($dsn, [0, '', '0', [], false, null], true)) {
            throw new InvalidArgumentException('Your DSN connection string is invalid.');
        }
        $dsn_params = ['DSN' => '', 'DBDriver' => $dsn['scheme'], 'hostname' => isset($dsn['host']) ? rawurldecode($dsn['host']) : '', 'port' => isset($dsn['port']) ? rawurldecode((string) $dsn['port']) : '', 'username' => isset($dsn['user']) ? rawurldecode($dsn['user']) : '', 'password' => isset($dsn['pass']) ? rawurldecode($dsn['pass']) : '', 'database' => isset($dsn['path']) ? rawurldecode(substr($dsn['path'], 1)) : ''];
        if (isset($dsn['query']) && $dsn['query'] !== '') {
            parse_str($dsn['query'], $extra);
            foreach ($extra as $key => $val) {
                if (is_string($val) && in_array(strtolower($val), ['true', 'false', 'null'], true)) {
                    $val = $val === 'null' ? null : filter_var($val, FILTER_VALIDATE_BOOLEAN);
                }
                $dsn_params[$key] = $val;
            }
        }
        return array_merge($params, $dsn_params);
    }
    /**
     * Creates a database object.
     *
     * @param string                    $driver   Driver name. FQCN can be used.
     * @param string                    $class    'Connection'|'Forge'|'Utils'
     * @param array|ConnectionInterface $argument The constructor parameter or DB connection
     *
     * @return BaseConnection|BaseUtils|Forge
     */
    protected function init_driver(string $driver, string $class, $argument): object
    {
        $classname = str_contains($driver, '\\') ? $driver . '\\' . $class : "CodeIgniter\\Database\\{$driver}\\{$class}";
        return new $classname($argument);
    }
    /**
     * Check the PHP database extension is loaded.
     *
     * @param string $driver DB driver or FQCN for custom driver
     */
    private function check_db_extension(string $driver): bool
    {
        if (str_contains($driver, '\\')) {
            // Cannot check a fully qualified classname for a custom driver.
            return true;
        }
        $extension_map = [
            // DBDriver => PHP extension
            'MySQLi' => 'mysqli',
            'SQLite3' => 'sqlite3',
            'Postgre' => 'pgsql',
            'SQLSRV' => 'sqlsrv',
            'OCI8' => 'oci8',
        ];
        $extension = $extension_map[$driver] ?? '';
        if ($extension === '') {
            $message = 'Invalid DBDriver name: "' . $driver . '"';
            throw new Config_Exception($message);
        }
        if (extension_loaded($extension)) {
            return true;
        }
        $message = 'The required PHP extension "' . $extension . '" is not loaded.' . ' Install and enable it to use "' . $driver . '" driver.';
        throw new Critical_Error($message);
    }
}