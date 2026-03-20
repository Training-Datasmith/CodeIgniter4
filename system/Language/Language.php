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
namespace Code_Igniter\Language;

use Intl_Exception;
use Message_Formatter;
/**
 * Handle system messages and localization.
 *
 * Locale-based, built on top of PHP internationalization.
 *
 * @phpstan-type LoadedStrings array<string, array<string, array<string, string>|string>|string|list<string>>
 *
 * @see \CodeIgniter\Language\LanguageTest
 */
class Language
{
    /**
     * Stores the retrieved language lines
     * from files for faster retrieval on
     * second use.
     *
     * @var array<non-empty-string, array<non-empty-string, LoadedStrings>>
     */
    protected $language = [];
    /**
     * The current locale to work with.
     *
     * @var non-empty-string
     */
    protected $locale;
    /**
     * Boolean value whether the `intl` extension exists on the system.
     *
     * @var bool
     */
    protected $intl_support = false;
    /**
     * Stores filenames that have been
     * loaded so that we don't load them again.
     *
     * @var array<non-empty-string, list<non-empty-string>>
     */
    protected $loaded_files = [];
    /**
     * @param non-empty-string $locale
     */
    public function __construct(string $locale)
    {
        $this->locale = $locale;
        if (class_exists(Message_Formatter::class)) {
            $this->intl_support = true;
        }
    }
    /**
     * Sets the current locale to use when performing string lookups.
     *
     * @param non-empty-string|null $locale
     *
     * @return $this
     */
    public function set_locale(?string $locale = null)
    {
        if ($locale !== null) {
            $this->locale = $locale;
        }
        return $this;
    }
    public function get_locale(): string
    {
        return $this->locale;
    }
    /**
     * Parses the language string for a file, loads the file, if necessary,
     * getting the line.
     *
     * @param array<array-key, float|int|string> $args
     *
     * @return list<string>|string
     */
    public function get_line(string $line, array $args = [])
    {
        // 1. Format the line as-is if it does not have a file.
        if (!str_contains($line, '.')) {
            return $this->format_message($line, $args);
        }
        // 2. Get the formatted line using the file and line extracted from $line and the current locale.
        [$file, $parsed_line] = $this->parse_line($line, $this->locale);
        $output = $this->get_translation_output($this->locale, $file, $parsed_line);
        // 3. If not found, try the locale without region (e.g., 'en-US' -> 'en').
        if ($output === null && str_contains($this->locale, '-')) {
            [$locale] = explode('-', $this->locale, 2);
            [$file, $parsed_line] = $this->parse_line($line, $locale);
            $output = $this->get_translation_output($locale, $file, $parsed_line);
        }
        // 4. If still not found, try English.
        if ($output === null) {
            [$file, $parsed_line] = $this->parse_line($line, 'en');
            $output = $this->get_translation_output('en', $file, $parsed_line);
        }
        // 5. Fallback to the original line if no translation was found.
        $output ??= $line;
        return $this->format_message($output, $args);
    }
    /**
     * @return list<string>|string|null
     */
    protected function get_translation_output(string $locale, string $file, string $parsed_line)
    {
        $output = $this->language[$locale][$file][$parsed_line] ?? null;
        if ($output !== null) {
            return $output;
        }
        // Fallback: try to traverse dot notation
        $current = $this->language[$locale][$file] ?? null;
        if (is_array($current)) {
            foreach (explode('.', $parsed_line) as $segment) {
                $output = $current[$segment] ?? null;
                if ($output === null) {
                    break;
                }
                if (is_array($output)) {
                    $current = $output;
                }
            }
            if ($output !== null && !is_array($output)) {
                return $output;
            }
        }
        // Final fallback: try two-level access manually
        [$first, $rest] = explode('.', $parsed_line, 2) + ['', ''];
        return $this->language[$locale][$file][$first][$rest] ?? null;
    }
    /**
     * Parses the language string which should include the
     * filename as the first segment (separated by period).
     *
     * @return array{non-empty-string, non-empty-string}
     */
    protected function parse_line(string $line, string $locale): array
    {
        [$file, $line] = explode('.', $line, 2);
        if (!isset($this->language[$locale][$file]) || !array_key_exists($line, $this->language[$locale][$file])) {
            $this->load($file, $locale);
        }
        return [$file, $line];
    }
    /**
     * Advanced message formatting.
     *
     * @param list<string>|string                $message
     * @param array<array-key, float|int|string> $args
     *
     * @return ($message is list<string> ? list<string> : string)
     */
    protected function format_message($message, array $args = [])
    {
        if (!$this->intl_support || $args === []) {
            return $message;
        }
        if (is_array($message)) {
            foreach ($message as $index => $value) {
                $message[$index] = $this->format_message($value, $args);
            }
            return $message;
        }
        $formatted = Message_Formatter::format_message($this->locale, $message, $args);
        if ($formatted === false) {
            // Format again to get the error message.
            try {
                $formatter = new Message_Formatter($this->locale, $message);
                $formatted = $formatter->format($args);
                $fmt_error = sprintf('"%s" (%d)', $formatter->get_error_message(), $formatter->get_error_code());
            } catch (Intl_Exception $e) {
                $fmt_error = sprintf('"%s" (%d)', $e->get_message(), $e->get_code());
            }
            $args_as_string = sprintf('"%s"', implode('", "', $args));
            $url_encoded_args = sprintf('"%s"', implode('", "', array_map(rawurlencode(...), $args)));
            log_message('error', sprintf('Invalid message format: $message: "%s", $args: %s (urlencoded: %s), MessageFormatter Error: %s', $message, $args_as_string, $url_encoded_args, $fmt_error));
            return $message . "\n【Warning】Also, invalid string(s) was passed to the Language class. See log file for details.";
        }
        return $formatted;
    }
    /**
     * Loads a language file in the current locale. If $return is true,
     * will return the file's contents, otherwise will merge with
     * the existing language lines.
     *
     * @return ($return is true ? LoadedStrings : null)
     */
    protected function load(string $file, string $locale, bool $return = false)
    {
        if (!array_key_exists($locale, $this->loaded_files)) {
            $this->loaded_files[$locale] = [];
        }
        if (in_array($file, $this->loaded_files[$locale], true)) {
            // Don't load it more than once.
            return [];
        }
        if (!array_key_exists($locale, $this->language)) {
            $this->language[$locale] = [];
        }
        if (!array_key_exists($file, $this->language[$locale])) {
            $this->language[$locale][$file] = [];
        }
        $path = "Language/{$locale}/{$file}.php";
        $lang = $this->require_file($path);
        if ($return) {
            return $lang;
        }
        $this->loaded_files[$locale][] = $file;
        // Merge our string
        $this->language[$locale][$file] = $lang;
        return null;
    }
    /**
     * A simple method for including files that can be overridden during testing.
     *
     * @return LoadedStrings
     */
    protected function require_file(string $path): array
    {
        $files = service('locator')->search($path, 'php', false);
        $strings = [];
        foreach ($files as $file) {
            if (is_file($file)) {
                // On some OS, we were seeing failures on this command returning boolean instead
                // of array during testing, so we've removed the require_once for now.
                $loaded_strings = require $file;
                if (is_array($loaded_strings)) {
                    /** @var LoadedStrings $loadedStrings */
                    $strings[] = $loaded_strings;
                }
            }
        }
        $count = count($strings);
        if ($count > 1) {
            $base = array_shift($strings);
            $strings = array_replace_recursive($base, ...$strings);
        } elseif ($count === 1) {
            $strings = $strings[0];
        }
        return $strings;
    }
}