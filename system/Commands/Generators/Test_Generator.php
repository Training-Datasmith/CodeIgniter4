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
class Test_Generator extends Base_Command
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
    protected $name = 'make:test';
    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a new test file.';
    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'make:test <name> [options]';
    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = ['name' => 'The test class name.'];
    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = ['--namespace' => 'Set root namespace. Default: "Tests".', '--force' => 'Force overwrite existing file.'];
    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        // Ensure tests are always suffixed with 'Test'
        $params['suffix'] = null;
        $this->component = 'Test';
        $this->template = 'test.tpl.php';
        $this->class_name_lang = 'CLI.generator.className.test';
        $autoload = service('autoloader');
        $autoload->add_namespace('CodeIgniter', TESTPATH . 'system');
        $autoload->add_namespace('Tests', ROOTPATH . 'tests');
        $this->generate_class($params);
    }
    /**
     * Gets the namespace from input or the default namespace.
     */
    protected function get_namespace(): string
    {
        if ($this->namespace !== null) {
            return $this->namespace;
        }
        if ($this->get_option('namespace') !== null) {
            return trim(str_replace('/', '\\', $this->get_option('namespace')), '\\');
        }
        $class = $this->normalize_input_class_name();
        $class_paths = explode('\\', $class);
        $namespaces = service('autoloader')->get_namespace();
        while ($class_paths !== []) {
            array_pop($class_paths);
            $namespace = implode('\\', $class_paths);
            foreach (array_keys($namespaces) as $prefix) {
                if ($prefix === $namespace) {
                    // The input classname is FQCN, and use the namespace.
                    return $namespace;
                }
            }
        }
        return 'Tests';
    }
    /**
     * Builds the test file path from the class name.
     *
     * @param string $class namespaced classname.
     */
    protected function build_path(string $class): string
    {
        $namespace = $this->get_namespace();
        $base = $this->search_test_file_path($namespace);
        if ($base === null) {
            CLI::error(lang('CLI.namespaceNotDefined', [$namespace]), 'light_gray', 'red');
            CLI::new_line();
            return '';
        }
        $realpath = realpath($base);
        $base = $realpath !== false ? $realpath : $base;
        $file = $base . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, trim(str_replace($namespace . '\\', '', $class), '\\')) . '.php';
        return implode(DIRECTORY_SEPARATOR, array_slice(explode(DIRECTORY_SEPARATOR, $file), 0, -1)) . DIRECTORY_SEPARATOR . $this->basename($file);
    }
    /**
     * Returns test file path for the namespace.
     */
    private function search_test_file_path(string $test_namespace): ?string
    {
        /** @var list<non-empty-string> $testPaths */
        $test_paths = service('autoloader')->get_namespace($test_namespace);
        foreach ($test_paths as $candidate) {
            if (str_contains($candidate, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)) {
                return $candidate;
            }
        }
        return null;
    }
}