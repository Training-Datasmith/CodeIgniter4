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
namespace Code_Igniter\Commands\Utilities;

use Code_Igniter\CLI\Base_Command;
use Code_Igniter\CLI\CLI;
use Config\Autoload;
/**
 * Lists namespaces set in Config\Autoload with their
 * full server path. Helps you to verify that you have
 * the namespaces setup correctly.
 *
 * @see \CodeIgniter\Commands\Utilities\NamespacesTest
 */
class Namespaces extends Base_Command
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
    protected $name = 'namespaces';
    /**
     * the Command's short description
     *
     * @var string
     */
    protected $description = 'Verifies your namespaces are setup correctly.';
    /**
     * the Command's usage
     *
     * @var string
     */
    protected $usage = 'namespaces';
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
    protected $options = ['-c' => 'Show only CodeIgniter config namespaces.', '-r' => 'Show raw path strings.', '-m' => 'Specify max length of the path strings to output. Default: 60.'];
    /**
     * Displays the help for the spark cli script itself.
     */
    public function run(array $params)
    {
        $params['m'] = (int) ($params['m'] ?? 60);
        $tbody = array_key_exists('c', $params) ? $this->output_ci_namespaces($params) : $this->output_all_namespaces($params);
        $thead = ['Namespace', 'Path', 'Found?'];
        CLI::table($tbody, $thead);
    }
    private function output_all_namespaces(array $params): array
    {
        $max_length = $params['m'];
        $autoloader = service('autoloader');
        $tbody = [];
        foreach ($autoloader->get_namespace() as $ns => $paths) {
            foreach ($paths as $path) {
                if (array_key_exists('r', $params)) {
                    $path_output = $this->truncate($path, $max_length);
                } else {
                    $path_output = $this->truncate(clean_path($path), $max_length);
                }
                $tbody[] = [$ns, $path_output, is_dir($path) ? 'Yes' : 'MISSING'];
            }
        }
        return $tbody;
    }
    private function truncate(string $string, int $max): string
    {
        $length = mb_strlen($string);
        if ($length > $max) {
            return mb_substr($string, 0, $max - 3) . '...';
        }
        return $string;
    }
    private function output_ci_namespaces(array $params): array
    {
        $max_length = $params['m'];
        $config = new Autoload();
        $tbody = [];
        foreach ($config->psr4 as $ns => $paths) {
            foreach ((array) $paths as $path) {
                if (array_key_exists('r', $params)) {
                    $path_output = $this->truncate($path, $max_length);
                } else {
                    $path_output = $this->truncate(clean_path($path), $max_length);
                }
                $path = realpath($path) ?: $path;
                $tbody[] = [$ns, $path_output, is_dir($path) ? 'Yes' : 'MISSING'];
            }
        }
        return $tbody;
    }
}