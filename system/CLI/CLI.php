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
namespace Code_Igniter\CLI;

use Code_Igniter\CLI\Exceptions\Cli_Exception;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Throwable;
/**
 * Set of static methods useful for CLI request handling.
 *
 * Portions of this code were initially from the FuelPHP Framework,
 * version 1.7.x, and used here under the MIT license they were
 * originally made available under. Reference: http://fuelphp.com
 *
 * Some of the code in this class is Windows-specific, and not
 * possible to test using travis-ci. It has been phpunit-annotated
 * to prevent messing up code coverage.
 *
 * @see \CodeIgniter\CLI\CLITest
 */
class CLI
{
    /**
     * Is the readline library on the system?
     *
     * @var bool
     *
     * @deprecated 4.4.2 Should be protected, and no longer used.
     * @TODO Fix to camelCase in the next major version.
     */
    public static $readline_support = false;
    /**
     * The message displayed at prompts.
     *
     * @var string
     *
     * @deprecated 4.4.2 Should be protected.
     * @TODO Fix to camelCase in the next major version.
     */
    public static $wait_msg = 'Press any key to continue...';
    /**
     * Has the class already been initialized?
     *
     * @var bool
     */
    protected static $initialized = false;
    /**
     * Foreground color list
     *
     * @var array<string, string>
     *
     * @TODO Fix to camelCase in the next major version.
     */
    protected static $foreground_colors = ['black' => '0;30', 'dark_gray' => '1;30', 'blue' => '0;34', 'dark_blue' => '0;34', 'light_blue' => '1;34', 'green' => '0;32', 'light_green' => '1;32', 'cyan' => '0;36', 'light_cyan' => '1;36', 'red' => '0;31', 'light_red' => '1;31', 'purple' => '0;35', 'light_purple' => '1;35', 'yellow' => '0;33', 'light_yellow' => '1;33', 'light_gray' => '0;37', 'white' => '1;37'];
    /**
     * Background color list
     *
     * @var array<string, string>
     *
     * @TODO Fix to camelCase in the next major version.
     */
    protected static $background_colors = ['black' => '40', 'red' => '41', 'green' => '42', 'yellow' => '43', 'blue' => '44', 'magenta' => '45', 'cyan' => '46', 'light_gray' => '47'];
    /**
     * List of array segments.
     *
     * @var list<string>
     */
    protected static $segments = [];
    /**
     * @var array<string, string|null>
     */
    protected static $options = [];
    /**
     * Helps track internally whether the last
     * output was a "write" or a "print" to
     * keep the output clean and as expected.
     *
     * @var string|null
     */
    protected static $last_write;
    /**
     * Height of the CLI window
     *
     * @var int|null
     */
    protected static $height;
    /**
     * Width of the CLI window
     *
     * @var int|null
     */
    protected static $width;
    /**
     * Whether the current stream supports colored output.
     *
     * @var bool
     */
    protected static $is_colored = false;
    /**
     * Input and Output for CLI.
     */
    protected static ?Input_Output $io = null;
    /**
     * Static "constructor".
     *
     * @return void
     */
    public static function init()
    {
        if (is_cli()) {
            // Readline is an extension for PHP that makes interactivity with PHP
            // much more bash-like.
            // http://www.php.net/manual/en/readline.installation.php
            static::$readline_support = extension_loaded('readline');
            // clear segments & options to keep testing clean
            static::$segments = [];
            static::$options = [];
            // Check our stream resource for color support
            static::$is_colored = static::has_color_support(STDOUT);
            static::parse_command_line();
            static::$initialized = true;
        } elseif (!defined('STDOUT')) {
            // If the command is being called from a controller
            // we need to define STDOUT ourselves
            // For "! defined('STDOUT')" see: https://github.com/codeigniter4/CodeIgniter4/issues/7047
            define('STDOUT', 'php://output');
            // @codeCoverageIgnore
        }
        static::reset_input_output();
    }
    /**
     * Get input from the shell, using readline or the standard STDIN
     *
     * Named options must be in the following formats:
     * php index.php user -v --v -name=John --name=John
     *
     * @param string|null $prefix You may specify a string with which to prompt the user.
     */
    public static function input(?string $prefix = null): string
    {
        return static::$io->input($prefix);
    }
    /**
     * Asks the user for input.
     *
     * Usage:
     *
     * // Takes any input
     * $color = CLI::prompt('What is your favorite color?');
     *
     * // Takes any input, but offers default
     * $color = CLI::prompt('What is your favourite color?', 'white');
     *
     * // Will validate options with the in_list rule and accept only if one of the list
     * $color = CLI::prompt('What is your favourite color?', array('red','blue'));
     *
     * // Do not provide options but requires a valid email
     * $email = CLI::prompt('What is your email?', null, 'required|valid_email');
     *
     * @param string                  $field      Output "field" question
     * @param list<int|string>|string $options    String to a default value, array to a list of options (the first option will be the default value)
     * @param array|string|null       $validation Validation rules
     *
     * @return string The user input
     */
    public static function prompt(string $field, $options = null, $validation = null): string
    {
        $extra_output = '';
        $default = '';
        if (isset($validation) && !is_array($validation) && !is_string($validation)) {
            throw new InvalidArgumentException('$rules can only be of type string|array');
        }
        if (!is_array($validation)) {
            $validation = $validation !== null ? explode('|', $validation) : [];
        }
        if (is_string($options)) {
            $extra_output = ' [' . static::color($options, 'green') . ']';
            $default = $options;
        }
        if (is_array($options) && $options !== []) {
            $opts = $options;
            $extra_output_default = static::color((string) $opts[0], 'green');
            unset($opts[0]);
            if ($opts === []) {
                $extra_output = $extra_output_default;
            } else {
                $extra_output = '[' . $extra_output_default . ', ' . implode(', ', $opts) . ']';
                $validation[] = 'in_list[' . implode(', ', $options) . ']';
            }
            $default = $options[0];
        }
        static::fwrite(STDOUT, $field . (trim($field) !== '' ? ' ' : '') . $extra_output . ': ');
        // Read the input from keyboard.
        $input = trim(static::$io->input());
        $input = $input === '' ? (string) $default : $input;
        if ($validation !== []) {
            while (!static::validate('"' . trim($field) . '"', $input, $validation)) {
                $input = static::prompt($field, $options, $validation);
            }
        }
        return $input;
    }
    /**
     * prompt(), but based on the option's key
     *
     * @param array|string      $text       Output "field" text or an one or two value array where the first value is the text before listing the options
     *                                      and the second value the text before asking to select one option. Provide empty string to omit
     * @param array             $options    A list of options (array(key => description)), the first option will be the default value
     * @param array|string|null $validation Validation rules
     *
     * @return string The selected key of $options
     */
    public static function prompt_by_key($text, array $options, $validation = null): string
    {
        if (is_string($text)) {
            $text = [$text];
        } elseif (!is_array($text)) {
            throw new InvalidArgumentException('$text can only be of type string|array');
        }
        CLI::is_zero_options($options);
        if (($line = array_shift($text)) !== null) {
            CLI::write($line);
        }
        CLI::print_keys_and_values($options);
        return static::prompt(PHP_EOL . array_shift($text), array_keys($options), $validation);
    }
    /**
     * This method is the same as promptByKey(), but this method supports multiple keys, separated by commas.
     *
     * @param string $text    Output "field" text or an one or two value array where the first value is the text before listing the options
     *                        and the second value the text before asking to select one option. Provide empty string to omit
     * @param array  $options A list of options (array(key => description)), the first option will be the default value
     *
     * @return array The selected key(s) and value(s) of $options
     */
    public static function prompt_by_multiple_keys(string $text, array $options): array
    {
        CLI::is_zero_options($options);
        $extra_output_default = static::color('0', 'green');
        $opts = $options;
        unset($opts[0]);
        if ($opts === []) {
            $extra_output = $extra_output_default;
        } else {
            $opts_key = array_keys($opts);
            $extra_output = '[' . $extra_output_default . ', ' . implode(', ', $opts_key) . ']';
            $extra_output = 'You can specify multiple values separated by commas.' . PHP_EOL . $extra_output;
        }
        CLI::write($text);
        CLI::print_keys_and_values($options);
        CLI::new_line();
        $input = static::prompt($extra_output);
        $input = $input === '' ? '0' : $input;
        // 0 is default
        // validation
        while (true) {
            $pattern = preg_match_all('/^\d+(,\d+)*$/', trim($input));
            // separate input by comma and convert all to an int[]
            $input_to_array = array_map(static fn($value): int => (int) $value, explode(',', $input));
            // find max from key of $options
            $max_options = array_key_last($options);
            // find max from input
            $max_input = max($input_to_array);
            // return the prompt again if $input contain(s) non-numeric character, except a comma.
            // And if max from $options less than max from input,
            // it means user tried to access null value in $options
            if ($pattern < 1 || $max_options < $max_input) {
                static::error('Please select correctly.');
                CLI::new_line();
                $input = static::prompt($extra_output);
                $input = $input === '' ? '0' : $input;
            } else {
                break;
            }
        }
        $input = [];
        foreach ($options as $key => $description) {
            foreach ($input_to_array as $input_key) {
                if ($key === $input_key) {
                    $input[$key] = $description;
                }
            }
        }
        return $input;
    }
    // --------------------------------------------------------------------
    // Utility for promptBy...
    // --------------------------------------------------------------------
    /**
     * Validation for $options in promptByKey() and promptByMultipleKeys(). Return an error if $options is an empty array.
     */
    private static function is_zero_options(array $options): void
    {
        if ($options === []) {
            throw new InvalidArgumentException('No options to select from were provided');
        }
    }
    /**
     * Print each key and value one by one
     */
    private static function print_keys_and_values(array $options): void
    {
        // +2 for the square brackets around the key
        $key_max_length = max(array_map(mb_strwidth(...), array_keys($options))) + 2;
        foreach ($options as $key => $description) {
            $name = str_pad('  [' . $key . ']  ', $key_max_length + 4, ' ');
            CLI::write(CLI::color($name, 'green') . CLI::wrap($description, 125, $key_max_length + 4));
        }
    }
    // --------------------------------------------------------------------
    // End Utility for promptBy...
    // --------------------------------------------------------------------
    /**
     * Validate one prompt "field" at a time
     *
     * @param string       $field Prompt "field" output
     * @param string       $value Input value
     * @param array|string $rules Validation rules
     */
    protected static function validate(string $field, string $value, $rules): bool
    {
        $label = $field;
        $field = 'temp';
        $validation = service('validation', null, false);
        $validation->set_rules([$field => ['label' => $label, 'rules' => $rules]]);
        $validation->run([$field => $value]);
        if ($validation->has_error($field)) {
            static::error($validation->get_error($field));
            return false;
        }
        return true;
    }
    /**
     * Outputs a string to the CLI without any surrounding newlines.
     * Useful for showing repeating elements on a single line.
     *
     * @return void
     */
    public static function print(string $text = '', ?string $foreground = null, ?string $background = null)
    {
        if ((string) $foreground !== '' || (string) $background !== '') {
            $text = static::color($text, $foreground, $background);
        }
        static::$last_write = null;
        static::fwrite(STDOUT, $text);
    }
    /**
     * Outputs a string to the cli on its own line.
     *
     * @return void
     */
    public static function write(string $text = '', ?string $foreground = null, ?string $background = null)
    {
        if ((string) $foreground !== '' || (string) $background !== '') {
            $text = static::color($text, $foreground, $background);
        }
        if (static::$last_write !== 'write') {
            $text = PHP_EOL . $text;
            static::$last_write = 'write';
        }
        static::fwrite(STDOUT, $text . PHP_EOL);
    }
    /**
     * Outputs an error to the CLI using STDERR instead of STDOUT
     *
     * @return void
     */
    public static function error(string $text, string $foreground = 'light_red', ?string $background = null)
    {
        // Check color support for STDERR
        $stdout = static::$is_colored;
        static::$is_colored = static::has_color_support(STDERR);
        if ($foreground !== '' || (string) $background !== '') {
            $text = static::color($text, $foreground, $background);
        }
        static::fwrite(STDERR, $text . PHP_EOL);
        // return STDOUT color support
        static::$is_colored = $stdout;
    }
    /**
     * Beeps a certain number of times.
     *
     * @param int $num The number of times to beep
     *
     * @return void
     */
    public static function beep(int $num = 1)
    {
        echo str_repeat("\x07", $num);
    }
    /**
     * Waits a certain number of seconds, optionally showing a wait message and
     * waiting for a key press.
     *
     * @param int  $seconds   Number of seconds
     * @param bool $countdown Show a countdown or not
     *
     * @return void
     */
    public static function wait(int $seconds, bool $countdown = false)
    {
        if ($countdown) {
            $time = $seconds;
            while ($time > 0) {
                static::fwrite(STDOUT, $time . '... ');
                sleep(1);
                $time--;
            }
            static::write();
        } elseif ($seconds > 0) {
            sleep($seconds);
        } else {
            static::write(static::$wait_msg);
            static::$io->input();
        }
    }
    /**
     * if operating system === windows
     *
     * @deprecated 4.3.0 Use `is_windows()` instead
     */
    public static function is_windows(): bool
    {
        return is_windows();
    }
    /**
     * Enter a number of empty lines
     *
     * @return void
     */
    public static function new_line(int $num = 1)
    {
        // Do it once or more, write with empty string gives us a new line
        for ($i = 0; $i < $num; $i++) {
            static::write();
        }
    }
    /**
     * Clears the screen of output
     *
     * @return void
     */
    public static function clear_screen()
    {
        // Unix systems, and Windows with VT100 Terminal support (i.e. Win10)
        // can handle CSI sequences. For lower than Win10 we just shove in 40 new lines.
        is_windows() && !static::stream_supports('sapi_windows_vt100_support', STDOUT) ? static::new_line(40) : static::fwrite(STDOUT, "\x1b[H\x1b[2J");
    }
    /**
     * Returns the given text with the correct color codes for a foreground and
     * optionally a background color.
     *
     * @param string      $text       The text to color
     * @param string      $foreground The foreground color
     * @param string|null $background The background color
     * @param string|null $format     Other formatting to apply. Currently only 'underline' is understood
     *
     * @return string The color coded string
     */
    public static function color(string $text, string $foreground, ?string $background = null, ?string $format = null): string
    {
        if (!static::$is_colored || $text === '') {
            return $text;
        }
        if (!array_key_exists($foreground, static::$foreground_colors)) {
            throw Cli_Exception::for_invalid_color('foreground', $foreground);
        }
        if ((string) $background !== '' && !array_key_exists($background, static::$background_colors)) {
            throw Cli_Exception::for_invalid_color('background', $background);
        }
        $new_text = '';
        // Detect if color method was already in use with this text
        if (str_contains($text, "\x1b[0m")) {
            $pattern = '/\033\[0;.+?\033\[0m/u';
            preg_match_all($pattern, $text, $matches);
            $colored_strings = $matches[0];
            // No colored string found. Invalid strings with no `\033[0;??`.
            if ($colored_strings === []) {
                return $new_text . self::get_colored_text($text, $foreground, $background, $format);
            }
            $non_colored_text = preg_replace($pattern, '<<__colored_string__>>', $text);
            $non_colored_chunks = preg_split('/<<__colored_string__>>/u', $non_colored_text);
            foreach ($non_colored_chunks as $i => $chunk) {
                if ($chunk !== '') {
                    $new_text .= self::get_colored_text($chunk, $foreground, $background, $format);
                }
                if (isset($colored_strings[$i])) {
                    $new_text .= $colored_strings[$i];
                }
            }
        } else {
            $new_text .= self::get_colored_text($text, $foreground, $background, $format);
        }
        return $new_text;
    }
    private static function get_colored_text(string $text, string $foreground, ?string $background, ?string $format): string
    {
        $string = "\x1b[" . static::$foreground_colors[$foreground] . 'm';
        if ((string) $background !== '') {
            $string .= "\x1b[" . static::$background_colors[$background] . 'm';
        }
        if ($format === 'underline') {
            $string .= "\x1b[4m";
        }
        return $string . $text . "\x1b[0m";
    }
    /**
     * Get the number of characters in string having encoded characters
     * and ignores styles set by the color() function
     */
    public static function strlen(?string $string): int
    {
        if ((string) $string === '') {
            return 0;
        }
        foreach (static::$foreground_colors as $color) {
            $string = strtr($string, ["\x1b[" . $color . 'm' => '']);
        }
        foreach (static::$background_colors as $color) {
            $string = strtr($string, ["\x1b[" . $color . 'm' => '']);
        }
        $string = strtr($string, ["\x1b[4m" => '', "\x1b[0m" => '']);
        return mb_strwidth($string);
    }
    /**
     * Checks whether the current stream resource supports or
     * refers to a valid terminal type device.
     *
     * @param resource $resource
     */
    public static function stream_supports(string $function, $resource): bool
    {
        if (ENVIRONMENT === 'testing') {
            // In the current setup of the tests we cannot fully check
            // if the stream supports the function since we are using
            // filtered streams.
            return function_exists($function);
        }
        return function_exists($function) && @$function($resource);
        // @codeCoverageIgnore
    }
    /**
     * Returns true if the stream resource supports colors.
     *
     * This is tricky on Windows, because Cygwin, Msys2 etc. emulate pseudo
     * terminals via named pipes, so we can only check the environment.
     *
     * Reference: https://github.com/composer/xdebug-handler/blob/master/src/Process.php
     *
     * @param resource $resource
     */
    public static function has_color_support($resource): bool
    {
        // Follow https://no-color.org/
        if (isset($_SERVER['NO_COLOR']) || getenv('NO_COLOR') !== false) {
            return false;
        }
        if (getenv('TERM_PROGRAM') === 'Hyper') {
            return true;
        }
        if (is_windows()) {
            // @codeCoverageIgnoreStart
            return static::stream_supports('sapi_windows_vt100_support', $resource) || isset($_SERVER['ANSICON']) || getenv('ANSICON') !== false || getenv('ConEmuANSI') === 'ON' || getenv('TERM') === 'xterm';
            // @codeCoverageIgnoreEnd
        }
        return static::stream_supports('stream_isatty', $resource);
    }
    /**
     * Attempts to determine the width of the viewable CLI window.
     */
    public static function get_width(int $default = 80): int
    {
        if (static::$width === null) {
            static::generate_dimensions();
        }
        return static::$width ?: $default;
    }
    /**
     * Attempts to determine the height of the viewable CLI window.
     */
    public static function get_height(int $default = 32): int
    {
        if (static::$height === null) {
            static::generate_dimensions();
        }
        return static::$height ?: $default;
    }
    /**
     * Populates the CLI's dimensions.
     *
     * @return void
     */
    public static function generate_dimensions()
    {
        try {
            if (is_windows()) {
                // Shells such as `Cygwin` and `Git bash` returns incorrect values
                // when executing `mode CON`, so we use `tput` instead
                if (getenv('TERM') || ($shell = getenv('SHELL')) && preg_match('/(?:bash|zsh)(?:\.exe)?$/', $shell)) {
                    static::$height = (int) exec('tput lines');
                    static::$width = (int) exec('tput cols');
                } else {
                    $return = -1;
                    $output = [];
                    exec('mode CON', $output, $return);
                    // Look for the next lines ending in ": <number>"
                    // Searching for "Columns:" or "Lines:" will fail on non-English locales
                    if ($return === 0 && $output !== [] && preg_match('/:\s*(\d+)\n[^:]+:\s*(\d+)\n/', implode("\n", $output), $matches)) {
                        static::$height = (int) $matches[1];
                        static::$width = (int) $matches[2];
                    }
                }
            } elseif (($size = exec('stty size')) && preg_match('/(\d+)\s+(\d+)/', $size, $matches)) {
                static::$height = (int) $matches[1];
                static::$width = (int) $matches[2];
            } else {
                static::$height = (int) exec('tput lines');
                static::$width = (int) exec('tput cols');
            }
        } catch (Throwable $e) {
            // Reset the dimensions so that the default values will be returned later.
            // Then let the developer know of the error.
            static::$height = null;
            static::$width = null;
            log_message('error', (string) $e);
        }
    }
    /**
     * Displays a progress bar on the CLI. You must call it repeatedly
     * to update it. Set $thisStep = false to erase the progress bar.
     *
     * @param bool|int $thisStep
     *
     * @return void
     */
    public static function show_progress($this_step = 1, int $total_steps = 10)
    {
        static $in_progress = false;
        // restore cursor position when progress is continuing.
        if ($in_progress !== false && $in_progress <= $this_step) {
            static::fwrite(STDOUT, "\x1b[1A");
        }
        $in_progress = $this_step;
        if ($this_step !== false) {
            // Don't allow div by zero or negative numbers....
            $this_step = abs($this_step);
            $total_steps = $total_steps < 1 ? 1 : $total_steps;
            $percent = (int) ($this_step / $total_steps * 100);
            $step = (int) round($percent / 10);
            // Write the progress bar
            static::fwrite(STDOUT, "[\x1b[32m" . str_repeat('#', $step) . str_repeat('.', 10 - $step) . "\x1b[0m]");
            // Textual representation...
            static::fwrite(STDOUT, sprintf(' %3d%% Complete', $percent) . PHP_EOL);
        } else {
            static::fwrite(STDOUT, "\x07");
        }
    }
    /**
     * Takes a string and writes it to the command line, wrapping to a maximum
     * width. If no maximum width is specified, will wrap to the window's max
     * width.
     *
     * If an int is passed into $pad_left, then all strings after the first
     * will pad with that many spaces to the left. Useful when printing
     * short descriptions that need to start on an existing line.
     */
    public static function wrap(?string $string = null, int $max = 0, int $pad_left = 0): string
    {
        if ((string) $string === '') {
            return '';
        }
        if ($max === 0) {
            $max = self::get_width();
        }
        if (self::get_width() < $max) {
            $max = self::get_width();
        }
        $max -= $pad_left;
        $lines = wordwrap($string, $max, PHP_EOL);
        if ($pad_left > 0) {
            $lines = explode(PHP_EOL, $lines);
            $first = true;
            array_walk($lines, static function (&$line) use ($pad_left, &$first): void {
                if (!$first) {
                    $line = str_repeat(' ', $pad_left) . $line;
                } else {
                    $first = false;
                }
            });
            $lines = implode(PHP_EOL, $lines);
        }
        return $lines;
    }
    // --------------------------------------------------------------------
    // Command-Line 'URI' support
    // --------------------------------------------------------------------
    /**
     * Parses the command line it was called from and collects all
     * options and valid segments.
     *
     * @return void
     */
    protected static function parse_command_line()
    {
        $args = $_SERVER['argv'] ?? [];
        array_shift($args);
        // scrap invoking program
        $option_value = false;
        foreach ($args as $i => $arg) {
            // If there's no "-" at the beginning, then
            // this is probably an argument or an option value
            if (mb_strpos($arg, '-') !== 0) {
                if ($option_value) {
                    // We have already included this in the previous
                    // iteration, so reset this flag
                    $option_value = false;
                } else {
                    // Yup, it's a segment
                    static::$segments[] = $arg;
                }
                continue;
            }
            $arg = ltrim($arg, '-');
            $value = null;
            if (isset($args[$i + 1]) && mb_strpos($args[$i + 1], '-') !== 0) {
                $value = $args[$i + 1];
                $option_value = true;
            }
            static::$options[$arg] = $value;
        }
    }
    /**
     * Returns the command line string portions of the arguments, minus
     * any options, as a string. This is used to pass along to the main
     * CodeIgniter application.
     */
    public static function get_uri(): string
    {
        return implode('/', static::$segments);
    }
    /**
     * Returns an individual segment.
     *
     * This ignores any options that might have been dispersed between
     * valid segments in the command:
     *
     *  // segment(3) is 'three', not '-f' or 'anOption'
     *  > php spark one two -f anOption three
     *
     * **IMPORTANT:** The index here is one-based instead of zero-based.
     *
     * @return string|null
     */
    public static function get_segment(int $index)
    {
        return static::$segments[$index - 1] ?? null;
    }
    /**
     * Returns the raw array of segments found.
     *
     * @return list<string>
     */
    public static function get_segments(): array
    {
        return static::$segments;
    }
    /**
     * Gets a single command-line option. Returns TRUE if the option
     * exists, but doesn't have a value, and is simply acting as a flag.
     *
     * @return string|true|null
     */
    public static function get_option(string $name)
    {
        if (!array_key_exists($name, static::$options)) {
            return null;
        }
        // If the option didn't have a value, simply return TRUE
        // so they know it was set, otherwise return the actual value.
        $val = static::$options[$name] ?? true;
        return $val;
    }
    /**
     * Returns the raw array of options found.
     *
     * @return array<string, string|null>
     */
    public static function get_options(): array
    {
        return static::$options;
    }
    /**
     * Returns the options as a string, suitable for passing along on
     * the CLI to other commands.
     *
     * @param bool $useLongOpts Use '--' for long options?
     * @param bool $trim        Trim final string output?
     */
    public static function get_option_string(bool $use_long_opts = false, bool $trim = false): string
    {
        if (static::$options === []) {
            return '';
        }
        $out = '';
        foreach (static::$options as $name => $value) {
            if ($use_long_opts && mb_strlen($name) > 1) {
                $out .= "--{$name} ";
            } else {
                $out .= "-{$name} ";
            }
            if ($value === null) {
                continue;
            }
            if (mb_strpos($value, ' ') !== false) {
                $out .= "\"{$value}\" ";
            } elseif ($value !== null) {
                $out .= "{$value} ";
            }
        }
        return $trim ? trim($out) : $out;
    }
    /**
     * Returns a well formatted table
     *
     * @param array $tbody List of rows
     * @param array $thead List of columns
     *
     * @return void
     */
    public static function table(array $tbody, array $thead = [])
    {
        // All the rows in the table will be here until the end
        $table_rows = [];
        // We need only indexes and not keys
        if ($thead !== []) {
            $table_rows[] = array_values($thead);
        }
        foreach ($tbody as $tr) {
            $table_rows[] = array_values($tr);
        }
        // Yes, it really is necessary to know this count
        $total_rows = count($table_rows);
        // Store all columns lengths
        // $all_cols_lengths[row][column] = length
        $all_cols_lengths = [];
        // Store maximum lengths by column
        // $max_cols_lengths[column] = length
        $max_cols_lengths = [];
        // Read row by row and define the longest columns
        for ($row = 0; $row < $total_rows; $row++) {
            $column = 0;
            // Current column index
            foreach ($table_rows[$row] as $col) {
                // Sets the size of this column in the current row
                $all_cols_lengths[$row][$column] = static::strlen((string) $col);
                // If the current column does not have a value among the larger ones
                // or the value of this is greater than the existing one
                // then, now, this assumes the maximum length
                if (!isset($max_cols_lengths[$column]) || $all_cols_lengths[$row][$column] > $max_cols_lengths[$column]) {
                    $max_cols_lengths[$column] = $all_cols_lengths[$row][$column];
                }
                // We can go check the size of the next column...
                $column++;
            }
        }
        // Read row by row and add spaces at the end of the columns
        // to match the exact column length
        for ($row = 0; $row < $total_rows; $row++) {
            $column = 0;
            foreach ($table_rows[$row] as $col) {
                $diff = $max_cols_lengths[$column] - static::strlen((string) $col);
                if ($diff !== 0) {
                    $table_rows[$row][$column] .= str_repeat(' ', $diff);
                }
                $column++;
            }
        }
        $table = '';
        $cols = '';
        // Joins columns and append the well formatted rows to the table
        for ($row = 0; $row < $total_rows; $row++) {
            // Set the table border-top
            if ($row === 0) {
                $cols = '+';
                foreach ($table_rows[$row] as $col) {
                    $cols .= str_repeat('-', static::strlen((string) $col) + 2) . '+';
                }
                $table .= $cols . PHP_EOL;
            }
            // Set the columns borders
            $table .= '| ' . implode(' | ', $table_rows[$row]) . ' |' . PHP_EOL;
            // Set the thead and table borders-bottom
            if ($row === 0 && $thead !== [] || $row + 1 === $total_rows) {
                $table .= $cols . PHP_EOL;
            }
        }
        static::write($table);
    }
    /**
     * While the library is intended for use on CLI commands,
     * commands can be called from controllers and elsewhere
     * so we need a way to allow them to still work.
     *
     * For now, just echo the content, but look into a better
     * solution down the road.
     *
     * @param resource $handle
     *
     * @return void
     */
    protected static function fwrite($handle, string $string)
    {
        static::$io->fwrite($handle, $string);
    }
    /**
     * Testing purpose only
     *
     * @internal
     */
    public static function reset(): void
    {
        static::$initialized = false;
        static::$segments = [];
        static::$options = [];
        static::$last_write = null;
        static::$height = null;
        static::$width = null;
        static::$is_colored = static::has_color_support(STDOUT);
        static::reset_input_output();
    }
    /**
     * Testing purpose only
     *
     * @internal
     */
    public static function reset_last_write(): void
    {
        static::$last_write = null;
    }
    /**
     * Testing purpose only
     *
     * @internal
     */
    public static function set_input_output(Input_Output $io): void
    {
        static::$io = $io;
    }
    /**
     * Testing purpose only
     *
     * @internal
     */
    public static function reset_input_output(): void
    {
        static::$io = new Input_Output();
    }
}
// Ensure the class is initialized. Done outside of code coverage
CLI::init();
// @codeCoverageIgnore