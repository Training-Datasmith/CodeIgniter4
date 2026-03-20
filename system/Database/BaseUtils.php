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

use Code_Igniter\Database\Exceptions\Database_Exception;
/**
 * Class BaseUtils
 */
abstract class Base_Utils
{
    /**
     * Database object
     *
     * @var object
     */
    protected $db;
    /**
     * List databases statement
     *
     * @var bool|string
     */
    protected $list_databases = false;
    /**
     * OPTIMIZE TABLE statement
     *
     * @var bool|string
     */
    protected $optimize_table = false;
    /**
     * REPAIR TABLE statement
     *
     * @var bool|string
     */
    protected $repair_table = false;
    /**
     * Class constructor
     */
    public function __construct(Connection_Interface $db)
    {
        $this->db = $db;
    }
    /**
     * List databases
     *
     * @return array|bool
     *
     * @throws DatabaseException
     */
    public function list_databases()
    {
        // Is there a cached result?
        if (isset($this->db->data_cache['db_names'])) {
            return $this->db->data_cache['db_names'];
        }
        if ($this->list_databases === false) {
            if ($this->db->db_debug) {
                throw new Database_Exception('Unsupported feature of the database platform you are using.');
            }
            return false;
        }
        $this->db->data_cache['db_names'] = [];
        $query = $this->db->query($this->list_databases);
        if ($query === false) {
            return $this->db->data_cache['db_names'];
        }
        for ($i = 0, $query = $query->get_result_array(), $c = count($query); $i < $c; $i++) {
            $this->db->data_cache['db_names'][] = current($query[$i]);
        }
        return $this->db->data_cache['db_names'];
    }
    /**
     * Determine if a particular database exists
     */
    public function database_exists(string $database_name): bool
    {
        return in_array($database_name, $this->list_databases(), true);
    }
    /**
     * Optimize Table
     *
     * @return bool
     *
     * @throws DatabaseException
     */
    public function optimize_table(string $table_name)
    {
        if ($this->optimize_table === false) {
            if ($this->db->db_debug) {
                throw new Database_Exception('Unsupported feature of the database platform you are using.');
            }
            return false;
        }
        $query = $this->db->query(sprintf($this->optimize_table, $this->db->escape_identifiers($table_name)));
        return $query !== false;
    }
    /**
     * Optimize Database
     *
     * @return mixed
     *
     * @throws DatabaseException
     */
    public function optimize_database()
    {
        if ($this->optimize_table === false) {
            if ($this->db->db_debug) {
                throw new Database_Exception('Unsupported feature of the database platform you are using.');
            }
            return false;
        }
        $result = [];
        foreach ($this->db->list_tables() as $table_name) {
            $res = $this->db->query(sprintf($this->optimize_table, $this->db->escape_identifiers($table_name)));
            if (is_bool($res)) {
                return $res;
            }
            // Build the result array...
            $res = $res->get_result_array();
            // Postgre & SQLite3 returns empty array
            if (empty($res)) {
                $key = $table_name;
            } else {
                $res = current($res);
                $key = str_replace($this->db->database . '.', '', current($res));
                $keys = array_keys($res);
                unset($res[$keys[0]]);
            }
            $result[$key] = $res;
        }
        return $result;
    }
    /**
     * Repair Table
     *
     * @return mixed
     *
     * @throws DatabaseException
     */
    public function repair_table(string $table_name)
    {
        if ($this->repair_table === false) {
            if ($this->db->db_debug) {
                throw new Database_Exception('Unsupported feature of the database platform you are using.');
            }
            return false;
        }
        $query = $this->db->query(sprintf($this->repair_table, $this->db->escape_identifiers($table_name)));
        if (is_bool($query)) {
            return $query;
        }
        $query = $query->get_result_array();
        return current($query);
    }
    /**
     * Generate CSV from a query result object
     *
     * @return string
     */
    public function get_csv_from_result(Result_Interface $query, string $delim = ',', string $newline = "\n", string $enclosure = '"')
    {
        $out = '';
        foreach ($query->get_field_names() as $name) {
            $out .= $enclosure . str_replace($enclosure, $enclosure . $enclosure, $name) . $enclosure . $delim;
        }
        $out = substr($out, 0, -strlen($delim)) . $newline;
        // Next blast through the result array and build out the rows
        while ($row = $query->get_unbuffered_row('array')) {
            $line = [];
            foreach ($row as $item) {
                $line[] = $enclosure . str_replace($enclosure, $enclosure . $enclosure, (string) $item) . $enclosure;
            }
            $out .= implode($delim, $line) . $newline;
        }
        return $out;
    }
    /**
     * Generate XML data from a query result object
     */
    public function get_xml_from_result(Result_Interface $query, array $params = []): string
    {
        foreach (['root' => 'root', 'element' => 'element', 'newline' => "\n", 'tab' => "\t"] as $key => $val) {
            if (!isset($params[$key])) {
                $params[$key] = $val;
            }
        }
        $root = $params['root'];
        $newline = $params['newline'];
        $tab = $params['tab'];
        $element = $params['element'];
        helper('xml');
        $xml = '<' . $root . '>' . $newline;
        while ($row = $query->get_unbuffered_row()) {
            $xml .= $tab . '<' . $element . '>' . $newline;
            foreach ($row as $key => $val) {
                $val = empty($val) ? '' : xml_convert((string) $val);
                $xml .= $tab . $tab . '<' . $key . '>' . $val . '</' . $key . '>' . $newline;
            }
            $xml .= $tab . '</' . $element . '>' . $newline;
        }
        return $xml . '</' . $root . '>' . $newline;
    }
    /**
     * Database Backup
     *
     * @param array|string $params
     *
     * @return false|never|string
     *
     * @throws DatabaseException
     */
    public function backup($params = [])
    {
        if (is_string($params)) {
            $params = ['tables' => $params];
        }
        $prefs = [
            'tables' => [],
            'ignore' => [],
            'filename' => '',
            'format' => 'gzip',
            // gzip, txt
            'add_drop' => true,
            'add_insert' => true,
            'newline' => "\n",
            'foreign_key_checks' => true,
        ];
        if (!empty($params)) {
            foreach (array_keys($prefs) as $key) {
                if (isset($params[$key])) {
                    $prefs[$key] = $params[$key];
                }
            }
        }
        if (empty($prefs['tables'])) {
            $prefs['tables'] = $this->db->list_tables();
        }
        if (!in_array($prefs['format'], ['gzip', 'txt'], true)) {
            $prefs['format'] = 'txt';
        }
        if ($prefs['format'] === 'gzip' && !function_exists('gzencode')) {
            if ($this->db->db_debug) {
                throw new Database_Exception('The file compression format you chose is not supported by your server.');
            }
            $prefs['format'] = 'txt';
        }
        if ($prefs['format'] === 'txt') {
            return $this->_backup($prefs);
        }
        // @TODO gzencode() requires `ext-zlib`, but _backup() is not implemented in all databases.
        return gzencode($this->_backup($prefs));
    }
    /**
     * Platform dependent version of the backup function.
     *
     * @return false|never|string
     */
    abstract public function _backup(?array $prefs = null);
}