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
namespace Code_Igniter\Commands\Database;

use Code_Igniter\CLI\Base_Command;
use Code_Igniter\CLI\CLI;
use Code_Igniter\Database\Base_Connection;
use Code_Igniter\Database\Table_Name;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Config\Database;
/**
 * Get table data if it exists in the database.
 *
 * @see \CodeIgniter\Commands\Database\ShowTableInfoTest
 */
class Show_Table_Info extends Base_Command
{
    /**
     * The group the command is lumped under
     * when listing commands.
     *
     * @var string
     */
    protected $group = 'Database';
    /**
     * The Command's name
     *
     * @var string
     */
    protected $name = 'db:table';
    /**
     * the Command's short description
     *
     * @var string
     */
    protected $description = 'Retrieves information on the selected table.';
    /**
     * the Command's usage
     *
     * @var string
     */
    protected $usage = <<<'EOL'
    db:table [<table_name>] [options]
    
      Examples:
        db:table --show
        db:table --metadata
        db:table my_table --metadata
        db:table my_table
        db:table my_table --limit-rows 5 --limit-field-value 10 --desc
    EOL;
    /**
     * The Command's arguments
     *
     * @var array<string, string>
     */
    protected $arguments = ['table_name' => 'The table name to show info'];
    /**
     * The Command's options
     *
     * @var array<string, string>
     */
    protected $options = ['--show' => 'Lists the names of all database tables.', '--metadata' => 'Retrieves list containing field information.', '--desc' => 'Sorts the table rows in DESC order.', '--limit-rows' => 'Limits the number of rows. Default: 10.', '--limit-field-value' => 'Limits the length of field values. Default: 15.', '--dbgroup' => 'Database group to show.'];
    /**
     * @var list<list<int|string>> Table Data.
     */
    private array $tbody;
    private ?Base_Connection $db = null;
    /**
     * @var bool Sort the table rows in DESC order or not.
     */
    private bool $sort_desc = false;
    private string $db_prefix;
    public function run(array $params)
    {
        $db_group = $params['dbgroup'] ?? CLI::get_option('dbgroup');
        try {
            $this->db = Database::connect($db_group);
        } catch (InvalidArgumentException $e) {
            CLI::error($e->get_message());
            return EXIT_ERROR;
        }
        $this->db_prefix = $this->db->get_prefix();
        $this->show_db_config();
        $tables = $this->db->list_tables();
        if (array_key_exists('desc', $params)) {
            $this->sort_desc = true;
        }
        if ($tables === []) {
            CLI::error('Database has no tables!', 'light_gray', 'red');
            CLI::new_line();
            return EXIT_ERROR;
        }
        if (array_key_exists('show', $params)) {
            $this->show_all_tables($tables);
            return EXIT_ERROR;
        }
        $table_name = $params[0] ?? null;
        $limit_rows = (int) ($params['limit-rows'] ?? 10);
        $limit_field_value = (int) ($params['limit-field-value'] ?? 15);
        while (!in_array($table_name, $tables, true)) {
            $table_name_no = CLI::prompt_by_key(['Here is the list of your database tables:', 'Which table do you want to see?'], $tables, 'required');
            CLI::new_line();
            $table_name = $tables[$table_name_no] ?? null;
        }
        if (array_key_exists('metadata', $params)) {
            $this->show_field_meta_data($table_name);
            return EXIT_SUCCESS;
        }
        $this->show_data_of_table($table_name, $limit_rows, $limit_field_value);
        return EXIT_SUCCESS;
    }
    private function show_db_config(): void
    {
        $data = [['hostname' => $this->db->hostname, 'database' => $this->db->get_database(), 'username' => $this->db->username, 'DBDriver' => $this->db->get_platform(), 'DBPrefix' => $this->db_prefix, 'port' => $this->db->port]];
        CLI::table($data, ['hostname', 'database', 'username', 'DBDriver', 'DBPrefix', 'port']);
    }
    private function remove_db_prefix(): void
    {
        $this->db->set_prefix('');
    }
    private function restore_db_prefix(): void
    {
        $this->db->set_prefix($this->db_prefix);
    }
    /**
     * Show Data of Table
     *
     * @return void
     */
    private function show_data_of_table(string $table_name, int $limit_rows, int $limit_field_value)
    {
        CLI::write("Data of Table \"{$table_name}\":", 'black', 'yellow');
        CLI::new_line();
        $this->remove_db_prefix();
        $thead = $this->db->get_field_names(Table_Name::from_actual_name($this->db->db_prefix, $table_name));
        $this->restore_db_prefix();
        // If there is a field named `id`, sort by it.
        $sort_field = null;
        if (in_array('id', $thead, true)) {
            $sort_field = 'id';
        }
        $this->tbody = $this->make_table_rows($table_name, $limit_rows, $limit_field_value, $sort_field);
        CLI::table($this->tbody, $thead);
    }
    /**
     * Show All Tables
     *
     * @param list<string> $tables
     *
     * @return void
     */
    private function show_all_tables(array $tables)
    {
        CLI::write('The following is a list of the names of all database tables:', 'black', 'yellow');
        CLI::new_line();
        $thead = ['ID', 'Table Name', 'Num of Rows', 'Num of Fields'];
        $this->tbody = $this->make_tbody_for_show_all_tables($tables);
        CLI::table($this->tbody, $thead);
        CLI::new_line();
    }
    /**
     * Make body for table
     *
     * @param list<string> $tables
     *
     * @return list<list<int|string>>
     */
    private function make_tbody_for_show_all_tables(array $tables): array
    {
        $this->remove_db_prefix();
        foreach ($tables as $id => $table_name) {
            $table = $this->db->protect_identifiers($table_name);
            $db = $this->db->query("SELECT * FROM {$table}");
            $this->tbody[] = [$id + 1, $table_name, $db->get_num_rows(), $db->get_field_count()];
        }
        $this->restore_db_prefix();
        if ($this->sort_desc) {
            krsort($this->tbody);
        }
        return $this->tbody;
    }
    /**
     * Make table rows
     *
     * @return list<list<int|string>>
     */
    private function make_table_rows(string $table_name, int $limit_rows, int $limit_field_value, ?string $sort_field = null): array
    {
        $this->tbody = [];
        $this->remove_db_prefix();
        $builder = $this->db->table(Table_Name::from_actual_name($this->db->db_prefix, $table_name));
        $builder->limit($limit_rows);
        if ($sort_field !== null) {
            $builder->order_by($sort_field, $this->sort_desc ? 'DESC' : 'ASC');
        }
        $rows = $builder->get()->get_result_array();
        $this->restore_db_prefix();
        foreach ($rows as $row) {
            $row = array_map(static fn($item): string => mb_strlen((string) $item) > $limit_field_value ? mb_substr((string) $item, 0, $limit_field_value) . '...' : (string) $item, $row);
            $this->tbody[] = $row;
        }
        if ($sort_field === null && $this->sort_desc) {
            krsort($this->tbody);
        }
        return $this->tbody;
    }
    private function show_field_meta_data(string $table_name): void
    {
        CLI::write("List of Metadata Information in Table \"{$table_name}\":", 'black', 'yellow');
        CLI::new_line();
        $thead = ['Field Name', 'Type', 'Max Length', 'Nullable', 'Default', 'Primary Key'];
        $this->remove_db_prefix();
        $fields = $this->db->get_field_data($table_name);
        $this->restore_db_prefix();
        foreach ($fields as $row) {
            $this->tbody[] = [$row->name, $row->type, $row->max_length, isset($row->nullable) ? $this->set_yes_or_no($row->nullable) : 'n/a', $row->default, isset($row->primary_key) ? $this->set_yes_or_no($row->primary_key) : 'n/a'];
        }
        if ($this->sort_desc) {
            krsort($this->tbody);
        }
        CLI::table($this->tbody, $thead);
    }
    /**
     * @param bool|int|string|null $fieldValue
     */
    private function set_yes_or_no($field_value): string
    {
        if ((bool) $field_value) {
            return CLI::color('Yes', 'green');
        }
        return CLI::color('No', 'red');
    }
}