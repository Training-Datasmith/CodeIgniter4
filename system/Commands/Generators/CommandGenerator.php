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
namespace Code_Igniter\Commands\Generators;

use Code_Igniter\CLI\Base_Command;
use Code_Igniter\CLI\CLI;
use Code_Igniter\CLI\Generator_Trait;
/**
 * Generates a skeleton command file.
 */
class Command_Generator extends Base_Command
{
    use Generator_Trait;
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Generators';
    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'make:command';
    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a new spark command.';
    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'make:command <name> [options]';
    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = ['name' => 'The command class name.'];
    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = ['--command' => 'The command name. Default: "command:name"', '--type' => 'The command type. Options [basic, generator]. Default: "basic".', '--group' => 'The command group. Default: [basic -> "App", generator -> "Generators"].', '--namespace' => 'Set root namespace. Default: "APP_NAMESPACE".', '--suffix' => 'Append the component title to the class name (e.g. User => UserCommand).', '--force' => 'Force overwrite existing file.'];
    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        $this->component = 'Command';
        $this->directory = 'Commands';
        $this->template = 'command.tpl.php';
        $this->class_name_lang = 'CLI.generator.className.command';
        $this->generate_class($params);
    }
    /**
     * Prepare options and do the necessary replacements.
     */
    protected function prepare(string $class): string
    {
        $command = $this->get_option('command');
        $group = $this->get_option('group');
        $type = $this->get_option('type');
        $command = is_string($command) ? $command : 'command:name';
        $type = is_string($type) ? $type : 'basic';
        if (!in_array($type, ['basic', 'generator'], true)) {
            // @codeCoverageIgnoreStart
            $type = CLI::prompt(lang('CLI.generator.commandType'), ['basic', 'generator'], 'required');
            CLI::new_line();
            // @codeCoverageIgnoreEnd
        }
        if (!is_string($group)) {
            $group = $type === 'generator' ? 'Generators' : 'App';
        }
        return $this->parse_template($class, ['{group}', '{command}'], [$group, $command], ['type' => $type]);
    }
}