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
namespace Code_Igniter\Commands\Encryption;

use Code_Igniter\CLI\Base_Command;
use Code_Igniter\CLI\CLI;
use Code_Igniter\Config\Dot_Env;
use Code_Igniter\Encryption\Encryption;
use Config\Paths;
/**
 * Generates a new encryption key.
 */
class Generate_Key extends Base_Command
{
    /**
     * The Command's group.
     *
     * @var string
     */
    protected $group = 'Encryption';
    /**
     * The Command's name.
     *
     * @var string
     */
    protected $name = 'key:generate';
    /**
     * The Command's usage.
     *
     * @var string
     */
    protected $usage = 'key:generate [options]';
    /**
     * The Command's short description.
     *
     * @var string
     */
    protected $description = 'Generates a new encryption key and writes it in an `.env` file.';
    /**
     * The command's options
     *
     * @var array<string, string>
     */
    protected $options = ['--force' => 'Force overwrite existing key in `.env` file.', '--length' => 'The length of the random string that should be returned in bytes. Defaults to 32.', '--prefix' => 'Prefix to prepend to encoded key (either hex2bin or base64). Defaults to hex2bin.', '--show' => 'Shows the generated key in the terminal instead of storing in the `.env` file.'];
    /**
     * Actually execute the command.
     */
    public function run(array $params)
    {
        $prefix = $params['prefix'] ?? CLI::get_option('prefix');
        if (in_array($prefix, [null, true], true)) {
            $prefix = 'hex2bin';
        } elseif (!in_array($prefix, ['hex2bin', 'base64'], true)) {
            $prefix = CLI::prompt('Please provide a valid prefix to use.', ['hex2bin', 'base64'], 'required');
            // @codeCoverageIgnore
        }
        $length = $params['length'] ?? CLI::get_option('length');
        if (in_array($length, [null, true], true)) {
            $length = 32;
        }
        $encoded_key = $this->generate_random_key($prefix, $length);
        if (array_key_exists('show', $params) || (bool) CLI::get_option('show')) {
            CLI::write($encoded_key, 'yellow');
            CLI::new_line();
            return;
        }
        if (!$this->set_new_encryption_key($encoded_key, $params)) {
            CLI::write('Error in setting new encryption key to .env file.', 'light_gray', 'red');
            CLI::new_line();
            return;
        }
        // force DotEnv to reload the new env vars
        putenv('encryption.key');
        unset($_ENV['encryption.key'], $_SERVER['encryption.key']);
        $dotenv = new Dot_Env((new Paths())->env_directory ?? ROOTPATH);
        $dotenv->load();
        CLI::write('Application\'s new encryption key was successfully set.', 'green');
        CLI::new_line();
    }
    /**
     * Generates a key and encodes it.
     */
    protected function generate_random_key(string $prefix, int $length): string
    {
        $key = Encryption::create_key($length);
        if ($prefix === 'hex2bin') {
            return 'hex2bin:' . bin2hex($key);
        }
        return 'base64:' . base64_encode($key);
    }
    /**
     * Sets the new encryption key in your .env file.
     *
     * @param array<int|string, string|null> $params
     */
    protected function set_new_encryption_key(string $key, array $params): bool
    {
        $current_key = env('encryption.key', '');
        if ($current_key !== '' && !$this->confirm_overwrite($params)) {
            // Not yet testable since it requires keyboard input
            return false;
            // @codeCoverageIgnore
        }
        return $this->write_new_encryption_key_to_file($current_key, $key);
    }
    /**
     * Checks whether to overwrite existing encryption key.
     *
     * @param array<int|string, string|null> $params
     */
    protected function confirm_overwrite(array $params): bool
    {
        return array_key_exists('force', $params) || CLI::get_option('force') || CLI::prompt('Overwrite existing key?', ['n', 'y']) === 'y';
    }
    /**
     * Writes the new encryption key to .env file.
     */
    protected function write_new_encryption_key_to_file(string $old_key, string $new_key): bool
    {
        $base_env = ROOTPATH . 'env';
        $env_file = ((new Paths())->env_directory ?? ROOTPATH) . '.env';
        if (!is_file($env_file)) {
            if (!is_file($base_env)) {
                CLI::write('Both default shipped `env` file and custom `.env` are missing.', 'yellow');
                CLI::write('Here\'s your new key instead: ' . CLI::color($new_key, 'yellow'));
                CLI::new_line();
                return false;
            }
            copy($base_env, $env_file);
        }
        $old_file_contents = (string) file_get_contents($env_file);
        $replacement_key = "\nencryption.key = {$new_key}";
        if (!str_contains($old_file_contents, 'encryption.key')) {
            return file_put_contents($env_file, $replacement_key, FILE_APPEND) !== false;
        }
        $new_file_contents = preg_replace($this->key_pattern($old_key), $replacement_key, $old_file_contents);
        if ($new_file_contents === $old_file_contents) {
            $new_file_contents = preg_replace('/^[#\s]*encryption.key[=\s]*(?:hex2bin\:[a-f0-9]{64}|base64\:(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?)$/m', $replacement_key, $old_file_contents);
        }
        return file_put_contents($env_file, $new_file_contents) !== false;
    }
    /**
     * Get the regex of the current encryption key.
     */
    protected function key_pattern(string $old_key): string
    {
        $escaped = preg_quote($old_key, '/');
        if ($escaped !== '') {
            $escaped = "[{$escaped}]*";
        }
        return "/^[#\\s]*encryption.key[=\\s]*{$escaped}\$/m";
    }
}