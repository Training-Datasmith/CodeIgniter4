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
use Code_Igniter\CLI\Generator_Trait;
use Config\Generators;
/**
 * Generates a skeleton Cell and its view.
 */
class Cell_Generator extends Base_Command
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
    protected $name = 'make:cell';
    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a new Controlled Cell file and its view.';
    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'make:cell <name> [options]';
    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = ['name' => 'The Controlled Cell class name.'];
    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = ['--namespace' => 'Set root namespace. Default: "APP_NAMESPACE".', '--force' => 'Force overwrite existing file.'];
    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        $this->component = 'Cell';
        $this->directory = 'Cells';
        $params = array_merge($params, ['suffix' => null]);
        $this->template_path = config(Generators::class)->views[$this->name]['class'];
        $this->template = 'cell.tpl.php';
        $this->class_name_lang = 'CLI.generator.className.cell';
        $this->generate_class($params);
        $this->template_path = config(Generators::class)->views[$this->name]['view'];
        $this->template = 'cell_view.tpl.php';
        $this->class_name_lang = 'CLI.generator.viewName.cell';
        $class_name = $this->qualify_class_name();
        $view_name = decamelize(class_basename($class_name));
        $view_name = preg_replace('/([a-z][a-z0-9_\/\\\\]+)(_cell)$/i', '$1', $view_name) ?? $view_name;
        $namespace = substr($class_name, 0, strrpos($class_name, '\\') + 1);
        $this->generate_view($namespace . $view_name, $params);
        return 0;
    }
}