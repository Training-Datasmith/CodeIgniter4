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
namespace Code_Igniter\Commands;

use Code_Igniter\CLI\Base_Command;
use Code_Igniter\CLI\CLI;
/**
 * CI Help command for the spark script.
 *
 * Lists the basic usage information for the spark script,
 * and provides a way to list help for other commands.
 */
class List_Commands extends Base_Command
{
    /**
     * The group the command is lumped under
     * when listing commands.
     *
     * @var string
     */
    protected $group = 'CodeIgniter';
    /**
     * The Command's name
     *
     * @var string
     */
    protected $name = 'list';
    /**
     * the Command's short description
     *
     * @var string
     */
    protected $description = 'Lists the available commands.';
    /**
     * the Command's usage
     *
     * @var string
     */
    protected $usage = 'list';
    /**
     * the Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [];
    /**
     * the Command's Options
     *
     * @var array<string, string>
     */
    protected $options = ['--simple' => 'Prints a list of the commands with no other info'];
    /**
     * Displays the help for the spark cli script itself.
     *
     * @return int
     */
    public function run(array $params)
    {
        $commands = $this->commands->get_commands();
        ksort($commands);
        // Check for 'simple' format
        return array_key_exists('simple', $params) || CLI::get_option('simple') === true ? $this->list_simple($commands) : $this->list_full($commands);
    }
    /**
     * Lists the commands with accompanying info.
     *
     * @return int
     */
    protected function list_full(array $commands)
    {
        // Sort into buckets by group
        $groups = [];
        foreach ($commands as $title => $command) {
            if (!isset($groups[$command['group']])) {
                $groups[$command['group']] = [];
            }
            $groups[$command['group']][$title] = $command;
        }
        $length = max(array_map(strlen(...), array_keys($commands)));
        ksort($groups);
        // Display it all...
        foreach ($groups as $group => $commands) {
            CLI::write($group, 'yellow');
            foreach ($commands as $name => $command) {
                $name = $this->set_pad($name, $length, 2, 2);
                $output = CLI::color($name, 'green');
                if (isset($command['description'])) {
                    $output .= CLI::wrap($command['description'], 125, strlen($name));
                }
                CLI::write($output);
            }
            if ($group !== array_key_last($groups)) {
                CLI::new_line();
            }
        }
        return EXIT_SUCCESS;
    }
    /**
     * Lists the commands only.
     *
     * @return int
     */
    protected function list_simple(array $commands)
    {
        foreach (array_keys($commands) as $title) {
            CLI::write($title);
        }
        return EXIT_SUCCESS;
    }
}