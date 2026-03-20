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
namespace Code_Igniter\Debug;

use Code_Igniter\HTTP\Cli_Request;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Config\Exceptions as ExceptionsConfig;
use Throwable;
/**
 * Provides common functions for exception handlers,
 * especially around displaying the output.
 */
abstract class Base_Exception_Handler
{
    /**
     * Config for debug exceptions.
     */
    protected Exceptions_Config $config;
    /**
     * Nesting level of the output buffering mechanism
     */
    protected int $ob_level;
    /**
     * The path to the directory containing the
     * cli and html error view directories.
     */
    protected ?string $view_path = null;
    public function __construct(Exceptions_Config $config)
    {
        $this->config = $config;
        $this->ob_level = ob_get_level();
        if ($this->view_path === null) {
            $this->view_path = rtrim($this->config->error_view_path, '\/ ') . DIRECTORY_SEPARATOR;
        }
    }
    /**
     * The main entry point into the handler.
     *
     * @param CLIRequest|IncomingRequest $request
     *
     * @return void
     */
    abstract public function handle(Throwable $exception, Request_Interface $request, Response_Interface $response, int $status_code, int $exit_code);
    /**
     * Gathers the variables that will be made available to the view.
     */
    protected function collect_vars(Throwable $exception, int $status_code): array
    {
        // Get the first exception.
        $first_exception = $exception;
        while ($prev_exception = $first_exception->get_previous()) {
            $first_exception = $prev_exception;
        }
        $trace = $first_exception->get_trace();
        if ($this->config->sensitive_data_in_trace !== []) {
            $trace = $this->mask_sensitive_data($trace, $this->config->sensitive_data_in_trace);
        }
        return ['title' => $exception::class, 'type' => $exception::class, 'code' => $status_code, 'message' => $exception->get_message(), 'file' => $exception->get_file(), 'line' => $exception->get_line(), 'trace' => $trace];
    }
    /**
     * Mask sensitive data in the trace.
     */
    protected function mask_sensitive_data(array $trace, array $keys_to_mask, string $path = ''): array
    {
        foreach ($trace as $i => $line) {
            $trace[$i]['args'] = $this->mask_data($line['args'], $keys_to_mask);
        }
        return $trace;
    }
    /**
     * @param array|object $args
     *
     * @return array|object
     */
    private function mask_data($args, array $keys_to_mask, string $path = '')
    {
        foreach ($keys_to_mask as $key_to_mask) {
            $explode = explode('/', $key_to_mask);
            $index = end($explode);
            if (str_starts_with(strrev($path . '/' . $index), strrev($key_to_mask))) {
                if (is_array($args) && array_key_exists($index, $args)) {
                    $args[$index] = '******************';
                } elseif (is_object($args) && property_exists($args, $index) && isset($args->{$index}) && is_scalar($args->{$index})) {
                    $args->{$index} = '******************';
                }
            }
        }
        if (is_array($args)) {
            foreach ($args as $path_key => $subarray) {
                $args[$path_key] = $this->mask_data($subarray, $keys_to_mask, $path . '/' . $path_key);
            }
        } elseif (is_object($args)) {
            foreach ($args as $path_key => $subarray) {
                $args->{$path_key} = $this->mask_data($subarray, $keys_to_mask, $path . '/' . $path_key);
            }
        }
        return $args;
    }
    /**
     * Describes memory usage in real-world units. Intended for use
     * with memory_get_usage, etc.
     *
     * @used-by app/Views/errors/html/error_exception.php
     */
    protected static function describe_memory(int $bytes): string
    {
        helper('number');
        return number_to_size($bytes, 2);
    }
    /**
     * Creates a syntax-highlighted version of a PHP file.
     *
     * @used-by app/Views/errors/html/error_exception.php
     *
     * @return bool|string
     */
    protected static function highlight_file(string $file, int $line_number, int $lines = 15)
    {
        if ($file === '' || !is_readable($file)) {
            return false;
        }
        // Set our highlight colors:
        if (function_exists('ini_set')) {
            ini_set('highlight.comment', '#767a7e; font-style: italic');
            ini_set('highlight.default', '#c7c7c7');
            ini_set('highlight.html', '#06B');
            ini_set('highlight.keyword', '#f1ce61;');
            ini_set('highlight.string', '#869d6a');
        }
        try {
            $source = file_get_contents($file);
        } catch (Throwable) {
            return false;
        }
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        $source = explode("\n", highlight_string($source, true));
        if (PHP_VERSION_ID < 80300) {
            $source = str_replace('<br />', "\n", $source[1]);
            $source = explode("\n", str_replace("\r\n", "\n", $source));
        } else {
            // We have to remove these tags since we're preparing the result
            // ourselves and these tags are added manually at the end.
            $source = str_replace(['<pre><code>', '</code></pre>'], '', $source);
        }
        // Get just the part to show
        $start = max($line_number - (int) round($lines / 2), 0);
        // Get just the lines we need to display, while keeping line numbers...
        $source = array_splice($source, $start, $lines, true);
        // Used to format the line number in the source
        $format = '% ' . strlen((string) ($start + $lines)) . 'd';
        $out = '';
        // Because the highlighting may have an uneven number
        // of open and close span tags on one line, we need
        // to ensure we can close them all to get the lines
        // showing correctly.
        $spans = 0;
        foreach ($source as $n => $row) {
            $spans += substr_count($row, '<span') - substr_count($row, '</span');
            $row = str_replace(["\r", "\n"], ['', ''], $row);
            if ($n + $start + 1 === $line_number) {
                preg_match_all('#<[^>]+>#', $row, $tags);
                $out .= sprintf("<span class='line highlight'><span class='number'>{$format}</span> %s\n</span>%s", $n + $start + 1, strip_tags($row), implode('', $tags[0]));
            } else {
                $out .= sprintf('<span class="line"><span class="number">' . $format . '</span> %s', $n + $start + 1, $row) . "\n";
                // We're closing only one span tag we added manually line before,
                // so we have to increment $spans count to close this tag later.
                $spans++;
            }
        }
        if ($spans > 0) {
            $out .= str_repeat('</span>', $spans);
        }
        return '<pre><code>' . $out . '</code></pre>';
    }
    /**
     * Given an exception and status code will display the error to the client.
     *
     * @param string|null $viewFile
     */
    protected function render(Throwable $exception, int $status_code, $view_file = null): void
    {
        if ($view_file === null) {
            echo 'The error view file was not specified. Cannot display error view.';
            exit(1);
        }
        if (!is_file($view_file)) {
            echo 'The error view file "' . $view_file . '" was not found. Cannot display error view.';
            exit(1);
        }
        echo (function () use ($exception, $status_code, $view_file): string {
            $vars = $this->collect_vars($exception, $status_code);
            extract($vars, EXTR_SKIP);
            // CLI error views output to STDERR/STDOUT, so ob_start() does not work.
            ob_start();
            include $view_file;
            return ob_get_clean();
        })();
    }
}