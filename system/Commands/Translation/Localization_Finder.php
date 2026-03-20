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
use Code_Igniter\Helpers\Array\Array_Helper;
use Config\App;
use Locale;
use Recursive_Directory_Iterator;
use Recursive_Iterator_Iterator;
use Spl_File_Info;
/**
 * @see \CodeIgniter\Commands\Translation\LocalizationFinderTest
 */
class Localization_Finder extends Base_Command
{
    protected $group = 'Translation';
    protected $name = 'lang:find';
    protected $description = 'Find and save available phrases to translate.';
    protected $usage = 'lang:find [options]';
    protected $arguments = [];
    protected $options = ['--locale' => 'Specify locale (en, ru, etc.) to save files.', '--dir' => 'Directory to search for translations relative to APPPATH.', '--show-new' => 'Show only new translations in table. Does not write to files.', '--verbose' => 'Output detailed information.'];
    /**
     * Flag for output detailed information
     */
    private bool $verbose = false;
    /**
     * Flag for showing only translations, without saving
     */
    private bool $show_new = false;
    private string $language_path;
    public function run(array $params)
    {
        $this->verbose = array_key_exists('verbose', $params);
        $this->show_new = array_key_exists('show-new', $params);
        $option_locale = $params['locale'] ?? null;
        $option_dir = $params['dir'] ?? null;
        $current_locale = Locale::get_default();
        $current_dir = APPPATH;
        $this->language_path = $current_dir . 'Language';
        if (ENVIRONMENT === 'testing') {
            $current_dir = SUPPORTPATH . 'Services' . DIRECTORY_SEPARATOR;
            $this->language_path = SUPPORTPATH . 'Language';
        }
        if (is_string($option_locale)) {
            if (!in_array($option_locale, config(App::class)->supported_locales, true)) {
                CLI::error('Error: "' . $option_locale . '" is not supported. Supported locales: ' . implode(', ', config(App::class)->supported_locales));
                return EXIT_USER_INPUT;
            }
            $current_locale = $option_locale;
        }
        if (is_string($option_dir)) {
            $temp_current_dir = realpath($current_dir . $option_dir);
            if ($temp_current_dir === false) {
                CLI::error('Error: Directory must be located in "' . $current_dir . '"');
                return EXIT_USER_INPUT;
            }
            if ($this->is_sub_directory($temp_current_dir, $this->language_path)) {
                CLI::error('Error: Directory "' . $this->language_path . '" restricted to scan.');
                return EXIT_USER_INPUT;
            }
            $current_dir = $temp_current_dir;
        }
        $this->process($current_dir, $current_locale);
        CLI::write('All operations done!');
        return EXIT_SUCCESS;
    }
    private function process(string $current_dir, string $current_locale): void
    {
        $table_rows = [];
        $count_new_keys = 0;
        $iterator = new Recursive_Iterator_Iterator(new Recursive_Directory_Iterator($current_dir));
        $files = iterator_to_array($iterator, true);
        ksort($files);
        ['foundLanguageKeys' => $found_language_keys, 'badLanguageKeys' => $bad_language_keys, 'countFiles' => $count_files] = $this->find_language_keys_in_files($files);
        ksort($found_language_keys);
        $language_diff = [];
        $language_found_groups = array_unique(array_keys($found_language_keys));
        foreach ($language_found_groups as $lang_file_name) {
            $language_stored_keys = [];
            $language_file_path = $this->language_path . DIRECTORY_SEPARATOR . $current_locale . DIRECTORY_SEPARATOR . $lang_file_name . '.php';
            if (is_file($language_file_path)) {
                // Load old localization
                $language_stored_keys = require $language_file_path;
            }
            $language_diff = Array_Helper::recursive_diff($found_language_keys[$lang_file_name], $language_stored_keys);
            $count_new_keys += Array_Helper::recursive_count($language_diff);
            if ($this->show_new) {
                $table_rows = array_merge($this->array_to_table_rows($lang_file_name, $language_diff), $table_rows);
            } else {
                $new_language_keys = array_replace_recursive($found_language_keys[$lang_file_name], $language_stored_keys);
                if ($language_diff !== []) {
                    if (file_put_contents($language_file_path, $this->template_file($new_language_keys)) === false) {
                        $this->write_is_verbose('Lang file ' . $lang_file_name . ' (error write).', 'red');
                    } else {
                        $this->write_is_verbose('Lang file "' . $lang_file_name . '" successful updated!', 'green');
                    }
                }
            }
        }
        if ($this->show_new && $table_rows !== []) {
            sort($table_rows);
            CLI::table($table_rows, ['File', 'Key']);
        }
        if (!$this->show_new && $count_new_keys > 0) {
            CLI::write('Note: You need to run your linting tool to fix coding standards issues.', 'white', 'red');
        }
        $this->write_is_verbose('Files found: ' . $count_files);
        $this->write_is_verbose('New translates found: ' . $count_new_keys);
        $this->write_is_verbose('Bad translates found: ' . count($bad_language_keys));
        if ($this->verbose && $bad_language_keys !== []) {
            $table_bad_rows = [];
            foreach ($bad_language_keys as $value) {
                $table_bad_rows[] = [$value[1], $value[0]];
            }
            Array_Helper::sort_values_by_natural($table_bad_rows, 0);
            CLI::table($table_bad_rows, ['Bad Key', 'Filepath']);
        }
    }
    /**
     * @param SplFileInfo|string $file
     *
     * @return array<string, array>
     */
    private function find_translations_in_file($file): array
    {
        $found_language_keys = [];
        $bad_language_keys = [];
        if (is_string($file) && is_file($file)) {
            $file = new Spl_File_Info($file);
        }
        $file_content = file_get_contents($file->get_real_path());
        preg_match_all('/lang\(\'([._a-z0-9\-]+)\'\)/ui', $file_content, $matches);
        if ($matches[1] === []) {
            return compact('foundLanguageKeys', 'badLanguageKeys');
        }
        foreach ($matches[1] as $phrase_key) {
            $phrase_keys = explode('.', $phrase_key);
            // Language key not have Filename or Lang key
            if (count($phrase_keys) < 2) {
                $bad_language_keys[] = [mb_substr($file->get_real_path(), mb_strlen(ROOTPATH)), $phrase_key];
                continue;
            }
            $language_file_name = array_shift($phrase_keys);
            $is_empty_nested_array = $language_file_name !== '' && $phrase_keys[0] === '' || $language_file_name === '' && $phrase_keys[0] !== '' || $language_file_name === '' && $phrase_keys[0] === '';
            if ($is_empty_nested_array) {
                $bad_language_keys[] = [mb_substr($file->get_real_path(), mb_strlen(ROOTPATH)), $phrase_key];
                continue;
            }
            if (count($phrase_keys) === 1) {
                $found_language_keys[$language_file_name][$phrase_keys[0]] = $phrase_key;
            } else {
                $child_keys = $this->build_multi_array($phrase_keys, $phrase_key);
                $found_language_keys[$language_file_name] = array_replace_recursive($found_language_keys[$language_file_name] ?? [], $child_keys);
            }
        }
        return compact('foundLanguageKeys', 'badLanguageKeys');
    }
    private function is_ignored_file(Spl_File_Info $file): bool
    {
        if ($file->is_dir() || $this->is_sub_directory($file->get_real_path(), $this->language_path)) {
            return true;
        }
        return $file->get_extension() !== 'php';
    }
    private function template_file(array $language = []): string
    {
        if ($language !== []) {
            $language_array_string = var_export($language, true);
            $code = <<<PHP
            <?php
            
            return {$language_array_string};
            
            PHP;
            return $this->replace_array_syntax($code);
        }
        return <<<'PHP'
        <?php
        
        return [];
        
        PHP;
    }
    private function replace_array_syntax(string $code): string
    {
        $tokens = token_get_all($code);
        $new_tokens = $tokens;
        foreach ($tokens as $i => $token) {
            if (is_array($token)) {
                [$token_id, $token_value] = $token;
                // Replace "array ("
                if ($token_id === T_ARRAY && $tokens[$i + 1][0] === T_WHITESPACE && $tokens[$i + 2] === '(') {
                    $new_tokens[$i][1] = '[';
                    $new_tokens[$i + 1][1] = '';
                    $new_tokens[$i + 2] = '';
                }
                // Replace indent
                if ($token_id === T_WHITESPACE && preg_match('/\n([ ]+)/u', $token_value, $matches)) {
                    $new_tokens[$i][1] = "\n{$matches[1]}{$matches[1]}";
                }
            } elseif ($token === ')') {
                $new_tokens[$i] = ']';
            }
        }
        $output = '';
        foreach ($new_tokens as $token) {
            $output .= $token[1] ?? $token;
        }
        return $output;
    }
    /**
     * Create multidimensional array from another keys
     */
    private function build_multi_array(array $from_keys, string $last_array_value = ''): array
    {
        $new_array = [];
        $last_index = array_pop($from_keys);
        $current =& $new_array;
        foreach ($from_keys as $value) {
            $current[$value] = [];
            $current =& $current[$value];
        }
        $current[$last_index] = $last_array_value;
        return $new_array;
    }
    /**
     * Convert multi arrays to specific CLI table rows (flat array)
     */
    private function array_to_table_rows(string $lang_file_name, array $array): array
    {
        $rows = [];
        foreach ($array as $value) {
            if (is_array($value)) {
                $rows = array_merge($rows, $this->array_to_table_rows($lang_file_name, $value));
                continue;
            }
            if (is_string($value)) {
                $rows[] = [$lang_file_name, $value];
            }
        }
        return $rows;
    }
    /**
     * Show details in the console if the flag is set
     */
    private function write_is_verbose(string $text = '', ?string $foreground = null, ?string $background = null): void
    {
        if ($this->verbose) {
            CLI::write($text, $foreground, $background);
        }
    }
    private function is_sub_directory(string $directory, string $root_directory): bool
    {
        return 0 === strncmp($directory, $root_directory, strlen($directory));
    }
    /**
     * @param list<SplFileInfo> $files
     *
     * @return array{'foundLanguageKeys': array<string, array<string, string>>, 'badLanguageKeys': array<int, array<int, string>>, 'countFiles': int}
     */
    private function find_language_keys_in_files(array $files): array
    {
        $found_language_keys = [];
        $bad_language_keys = [];
        $count_files = 0;
        foreach ($files as $file) {
            if ($this->is_ignored_file($file)) {
                continue;
            }
            $this->write_is_verbose('File found: ' . mb_substr($file->get_real_path(), mb_strlen(APPPATH)));
            $count_files++;
            $find_in_file = $this->find_translations_in_file($file);
            $found_language_keys = array_replace_recursive($find_in_file['foundLanguageKeys'], $found_language_keys);
            $bad_language_keys = array_merge($find_in_file['badLanguageKeys'], $bad_language_keys);
        }
        return compact('foundLanguageKeys', 'badLanguageKeys', 'countFiles');
    }
}