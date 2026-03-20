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
namespace Code_Igniter\Config;

use Code_Igniter\Exceptions\InvalidArgumentException;
/**
 * Environment-specific configuration
 *
 * @see \CodeIgniter\Config\DotEnvTest
 */
class Dot_Env
{
    /**
     * The directory where the .env file can be located.
     *
     * @var string
     */
    protected $path;
    /**
     * Builds the path to our file.
     */
    public function __construct(string $path, string $file = '.env')
    {
        $this->path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
    }
    /**
     * The main entry point, will load the .env file and process it
     * so that we end up with all settings in the PHP environment vars
     * (i.e. getenv(), $_ENV, and $_SERVER)
     */
    public function load(): bool
    {
        $vars = $this->parse();
        return $vars !== null;
    }
    /**
     * Parse the .env file into an array of key => value
     */
    public function parse(): ?array
    {
        // We don't want to enforce the presence of a .env file, they should be optional.
        if (!is_file($this->path)) {
            return null;
        }
        // Ensure the file is readable
        if (!is_readable($this->path)) {
            throw new InvalidArgumentException("The .env file is not readable: {$this->path}");
        }
        $vars = [];
        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Is it a comment?
            if (str_starts_with(trim($line), '#')) {
                continue;
            }
            // If there is an equal sign, then we know we are assigning a variable.
            if (str_contains($line, '=')) {
                [$name, $value] = $this->normalise_variable($line);
                $vars[$name] = $value;
                $this->set_variable($name, $value);
            }
        }
        return $vars;
    }
    /**
     * Sets the variable into the environment. Will parse the string
     * first to look for {name}={value} pattern, ensure that nested
     * variables are handled, and strip it of single and double quotes.
     *
     * @return void
     */
    protected function set_variable(string $name, string $value = '')
    {
        if (getenv($name, true) === false) {
            putenv("{$name}={$value}");
        }
        if (empty($_ENV[$name])) {
            $_ENV[$name] = $value;
        }
        if (empty($_SERVER[$name])) {
            $_SERVER[$name] = $value;
        }
    }
    /**
     * Parses for assignment, cleans the $name and $value, and ensures
     * that nested variables are handled.
     */
    public function normalise_variable(string $name, string $value = ''): array
    {
        // Split our compound string into its parts.
        if (str_contains($name, '=')) {
            [$name, $value] = explode('=', $name, 2);
        }
        $name = trim($name);
        $value = trim($value);
        // Sanitize the name
        $name = preg_replace('/^export[ \t]++(\S+)/', '$1', $name);
        $name = str_replace(['\'', '"'], '', $name);
        // Sanitize the value
        $value = $this->sanitize_value($value);
        $value = $this->resolve_nested_variables($value);
        return [$name, $value];
    }
    /**
     * Strips quotes from the environment variable value.
     *
     * This was borrowed from the excellent phpdotenv with very few changes.
     * https://github.com/vlucas/phpdotenv
     *
     * @throws InvalidArgumentException
     */
    protected function sanitize_value(string $value): string
    {
        if ($value === '') {
            return $value;
        }
        // Does it begin with a quote?
        if (strpbrk($value[0], '"\'') !== false) {
            // value starts with a quote
            $quote = $value[0];
            $regex_pattern = sprintf('/^
                %1$s          # match a quote at the start of the value
                (             # capturing sub-pattern used
                 (?:          # we do not need to capture this
                 [^%1$s\\\\] # any character other than a quote or backslash
                 |\\\\\\\\   # or two backslashes together
                 |\\\\%1$s   # or an escaped quote e.g \"
                 )*           # as many characters that match the previous rules
                )             # end of the capturing sub-pattern
                %1$s          # and the closing quote
                .*$           # and discard any string after the closing quote
                /mx', $quote);
            $value = preg_replace($regex_pattern, '$1', $value);
            $value = str_replace("\\{$quote}", $quote, $value);
            $value = str_replace('\\\\', '\\', $value);
        } else {
            $parts = explode(' #', $value, 2);
            $value = trim($parts[0]);
            // Unquoted values cannot contain whitespace
            if (preg_match('/\s+/', $value) > 0) {
                throw new InvalidArgumentException('.env values containing spaces must be surrounded by quotes.');
            }
        }
        return $value;
    }
    /**
     *  Resolve the nested variables.
     *
     * Look for ${varname} patterns in the variable value and replace with an existing
     * environment variable.
     *
     * This was borrowed from the excellent phpdotenv with very few changes.
     * https://github.com/vlucas/phpdotenv
     */
    protected function resolve_nested_variables(string $value): string
    {
        if (str_contains($value, '$')) {
            $value = preg_replace_callback('/\${([a-zA-Z0-9_\.]+)}/', function ($matched_patterns) {
                $nested_variable = $this->get_variable($matched_patterns[1]);
                if ($nested_variable === null) {
                    return $matched_patterns[0];
                }
                return $nested_variable;
            }, $value);
        }
        return $value;
    }
    /**
     * Search the different places for environment variables and return first value found.
     *
     * This was borrowed from the excellent phpdotenv with very few changes.
     * https://github.com/vlucas/phpdotenv
     *
     * @return string|null
     */
    protected function get_variable(string $name)
    {
        switch (true) {
            case array_key_exists($name, $_ENV):
                return $_ENV[$name];
            case array_key_exists($name, $_SERVER):
                return $_SERVER[$name];
            default:
                $value = getenv($name);
                // switch getenv default to null
                return $value === false ? null : $value;
        }
    }
}