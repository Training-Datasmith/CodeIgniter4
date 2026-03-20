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
 * Generates a skeleton Model file.
 */
class Model_Generator extends Base_Command
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
    protected $name = 'make:model';
    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a new model file.';
    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'make:model <name> [options]';
    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = ['name' => 'The model class name.'];
    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = ['--table' => 'Supply a table name. Default: "the lowercased plural of the class name".', '--dbgroup' => 'Database group to use. Default: "default".', '--return' => 'Return type, Options: [array, object, entity]. Default: "array".', '--namespace' => 'Set root namespace. Default: "APP_NAMESPACE".', '--suffix' => 'Append the component title to the class name (e.g. User => UserModel).', '--force' => 'Force overwrite existing file.'];
    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        $this->component = 'Model';
        $this->directory = 'Models';
        $this->template = 'model.tpl.php';
        $this->class_name_lang = 'CLI.generator.className.model';
        $this->generate_class($params);
    }
    /**
     * Prepare options and do the necessary replacements.
     */
    protected function prepare(string $class): string
    {
        $table = $this->get_option('table');
        $db_group = $this->get_option('dbgroup');
        $return = $this->get_option('return');
        $base_class = class_basename($class);
        if (preg_match('/^(\S+)Model$/i', $base_class, $match) === 1) {
            $base_class = $match[1];
        }
        $table = is_string($table) ? $table : plural(strtolower($base_class));
        $return = is_string($return) ? $return : 'array';
        if (!in_array($return, ['array', 'object', 'entity'], true)) {
            // @codeCoverageIgnoreStart
            $return = CLI::prompt(lang('CLI.generator.returnType'), ['array', 'object', 'entity'], 'required');
            CLI::new_line();
            // @codeCoverageIgnoreEnd
        }
        if ($return === 'entity') {
            $return = str_replace('Models', 'Entities', $class);
            if (preg_match('/^(\S+)Model$/i', $return, $match) === 1) {
                $return = $match[1];
                if ($this->get_option('suffix')) {
                    $return .= 'Entity';
                }
            }
            $return = '\\' . trim($return, '\\') . '::class';
            $this->call('make:entity', array_merge([$base_class], $this->params));
        } else {
            $return = "'{$return}'";
        }
        return $this->parse_template($class, ['{dbGroup}', '{table}', '{return}'], [$db_group, $table, $return], compact('dbGroup'));
    }
}