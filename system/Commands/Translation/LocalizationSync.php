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
namespace Code_Igniter\Commands\Translation;

use Code_Igniter\CLI\Base_Command;
use Code_Igniter\CLI\CLI;
use Code_Igniter\Exceptions\LogicException;
use Config\App;
use ErrorException;
use Filesystem_Iterator;
use Locale;
use Recursive_Directory_Iterator;
use Recursive_Iterator_Iterator;
use Spl_File_Info;
/**
 * @see \CodeIgniter\Commands\Translation\LocalizationSyncTest
 */
class Localization_Sync extends Base_Command
{
    protected $group = 'Translation';
    protected $name = 'lang:sync';
    protected $description = 'Synchronize translation files from one language to another.';
    protected $usage = 'lang:sync [options]';
    protected $arguments = [];
    protected $options = ['--locale' => 'The original locale (en, ru, etc.).', '--target' => 'Target locale (en, ru, etc.).'];
    private string $language_path;
    public function run(array $params)
    {
        $option_target_locale = '';
        $option_locale = $params['locale'] ?? Locale::get_default();
        $this->language_path = APPPATH . 'Language';
        if (isset($params['target']) && $params['target'] !== '') {
            $option_target_locale = $params['target'];
        }
        if (!in_array($option_locale, config(App::class)->supported_locales, true)) {
            CLI::error('Error: "' . $option_locale . '" is not supported. Supported locales: ' . implode(', ', config(App::class)->supported_locales));
            return EXIT_USER_INPUT;
        }
        if ($option_target_locale === '') {
            CLI::error('Error: "--target" is not configured. Supported locales: ' . implode(', ', config(App::class)->supported_locales));
            return EXIT_USER_INPUT;
        }
        if (!in_array($option_target_locale, config(App::class)->supported_locales, true)) {
            CLI::error('Error: "' . $option_target_locale . '" is not supported. Supported locales: ' . implode(', ', config(App::class)->supported_locales));
            return EXIT_USER_INPUT;
        }
        if ($option_target_locale === $option_locale) {
            CLI::error('Error: You cannot have the same values for "--target" and "--locale".');
            return EXIT_USER_INPUT;
        }
        if (ENVIRONMENT === 'testing') {
            $this->language_path = SUPPORTPATH . 'Language';
        }
        if ($this->process($option_locale, $option_target_locale) === EXIT_ERROR) {
            return EXIT_ERROR;
        }
        CLI::write('All operations done!');
        return EXIT_SUCCESS;
    }
    private function process(string $original_locale, string $target_locale): int
    {
        $original_locale_dir = $this->language_path . DIRECTORY_SEPARATOR . $original_locale;
        $target_locale_dir = $this->language_path . DIRECTORY_SEPARATOR . $target_locale;
        if (!is_dir($original_locale_dir)) {
            CLI::error('Error: The "' . clean_path($original_locale_dir) . '" directory was not found.');
            return EXIT_ERROR;
        }
        // Unifying the error - mkdir() may cause an exception.
        try {
            if (!is_dir($target_locale_dir) && !mkdir($target_locale_dir, 0775)) {
                throw new ErrorException();
            }
        } catch (ErrorException $e) {
            CLI::error('Error: The target directory "' . clean_path($target_locale_dir) . '" cannot be accessed.');
            return EXIT_ERROR;
        }
        $iterator = new Recursive_Iterator_Iterator(new Recursive_Directory_Iterator($original_locale_dir, Filesystem_Iterator::KEY_AS_PATHNAME | Filesystem_Iterator::CURRENT_AS_FILEINFO | Filesystem_Iterator::SKIP_DOTS));
        /**
         * @var array<non-empty-string, SplFileInfo> $files
         */
        $files = iterator_to_array($iterator, true);
        ksort($files);
        foreach ($files as $original_language_file) {
            if ($original_language_file->get_extension() !== 'php') {
                continue;
            }
            $target_language_file = $target_locale_dir . DIRECTORY_SEPARATOR . $original_language_file->get_filename();
            $target_language_keys = [];
            $original_language_keys = include $original_language_file;
            if (is_file($target_language_file)) {
                $target_language_keys = include $target_language_file;
            }
            $target_language_keys = $this->merge_language_keys($original_language_keys, $target_language_keys, $original_language_file->get_basename('.php'));
            $content = "<?php\n\nreturn " . var_export($target_language_keys, true) . ";\n";
            file_put_contents($target_language_file, $content);
        }
        return EXIT_SUCCESS;
    }
    /**
     * @param array<string, array<string,mixed>|string|null> $originalLanguageKeys
     * @param array<string, array<string,mixed>|string|null> $targetLanguageKeys
     *
     * @return array<string, array<string,mixed>|string|null>
     */
    private function merge_language_keys(array $original_language_keys, array $target_language_keys, string $prefix = ''): array
    {
        $merged_language_keys = [];
        foreach ($original_language_keys as $key => $value) {
            $placeholder_value = $prefix !== '' ? $prefix . '.' . $key : $key;
            if (is_string($value)) {
                // Keep the old value
                // TODO: The value type may not match the original one
                if (array_key_exists($key, $target_language_keys)) {
                    $merged_language_keys[$key] = $target_language_keys[$key];
                    continue;
                }
                // Set new key with placeholder
                $merged_language_keys[$key] = $placeholder_value;
            } elseif (is_array($value)) {
                if (!array_key_exists($key, $target_language_keys)) {
                    $merged_language_keys[$key] = $this->merge_language_keys($value, [], $placeholder_value);
                    continue;
                }
                $merged_language_keys[$key] = $this->merge_language_keys($value, $target_language_keys[$key], $placeholder_value);
            } else {
                throw new LogicException('Value for the key "' . $placeholder_value . '" is of the wrong type. Only "array" or "string" is allowed.');
            }
        }
        return $merged_language_keys;
    }
}