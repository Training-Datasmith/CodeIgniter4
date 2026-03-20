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
namespace Code_Igniter\Commands\Worker;

use Code_Igniter\CLI\Base_Command;
use Code_Igniter\CLI\CLI;
/**
 * Install Worker Mode for FrankenPHP.
 *
 * This command sets up the necessary files to run CodeIgniter 4
 * in FrankenPHP worker mode for improved performance.
 */
class Worker_Install extends Base_Command
{
    protected $group = 'Worker Mode';
    protected $name = 'worker:install';
    protected $description = 'Install FrankenPHP worker mode by creating necessary configuration files';
    protected $usage = 'worker:install [options]';
    protected $options = ['--force' => 'Overwrite existing files'];
    /**
     * Template file mappings (template => destination path)
     *
     * @var array<string, string>
     */
    private array $templates = ['frankenphp-worker.php.tpl' => 'public/frankenphp-worker.php', 'Caddyfile.tpl' => 'Caddyfile'];
    public function run(array $params)
    {
        $force = array_key_exists('force', $params) || CLI::get_option('force');
        CLI::write('Setting up FrankenPHP Worker Mode', 'yellow');
        CLI::new_line();
        helper('filesystem');
        $created = [];
        // Process each template
        foreach ($this->templates as $template => $destination) {
            $source = SYSTEMPATH . 'Commands/Worker/Views/' . $template;
            $target = ROOTPATH . $destination;
            $is_file = is_file($target);
            // Skip if file exists and not forcing overwrite
            if (!$force && $is_file) {
                continue;
            }
            // Read template content
            $content = file_get_contents($source);
            if ($content === false) {
                CLI::error("Failed to read template: {$template}", 'light_gray', 'red');
                CLI::new_line();
                return EXIT_ERROR;
            }
            // Write file to destination
            if (!write_file($target, $content)) {
                CLI::error('Failed to create file: ' . clean_path($target), 'light_gray', 'red');
                CLI::new_line();
                return EXIT_ERROR;
            }
            if ($force && $is_file) {
                CLI::write('  File overwritten: ' . clean_path($target), 'yellow');
            } else {
                CLI::write('  File created: ' . clean_path($target), 'green');
            }
            $created[] = $destination;
        }
        // No files were created
        if ($created === []) {
            CLI::new_line();
            CLI::write('Worker mode files already exist.', 'yellow');
            CLI::write('Use --force to overwrite existing files.', 'yellow');
            CLI::new_line();
            return EXIT_ERROR;
        }
        // Success message
        CLI::new_line();
        CLI::write('Worker mode files created successfully!', 'green');
        CLI::new_line();
        $this->show_next_steps();
        return EXIT_SUCCESS;
    }
    /**
     * Display next steps to the user
     */
    protected function show_next_steps(): void
    {
        CLI::write('Next Steps:', 'yellow');
        CLI::new_line();
        CLI::write('1. Start FrankenPHP:', 'white');
        CLI::write('   frankenphp run', 'green');
        CLI::new_line();
        CLI::write('2. Test your application:', 'white');
        CLI::write('   curl http://localhost:8080/', 'green');
        CLI::new_line();
    }
}