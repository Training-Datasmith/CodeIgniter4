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

use Code_Igniter\API\Response_Trait;
use Code_Igniter\Exceptions\Has_Exit_Code_Interface;
use Code_Igniter\Exceptions\Http_Exception_Interface;
use Code_Igniter\Exceptions\Page_Not_Found_Exception;
use Code_Igniter\HTTP\Exceptions\Http_Exception;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Config\Exceptions as ExceptionsConfig;
use Config\Paths;
use ErrorException;
use Psr\Log\Log_Level;
use Throwable;
/**
 * Exceptions manager
 *
 * @see \CodeIgniter\Debug\ExceptionsTest
 */
class Exceptions
{
    use Response_Trait;
    /**
     * Nesting level of the output buffering mechanism
     *
     * @var int
     *
     * @deprecated 4.4.0 No longer used. Moved to BaseExceptionHandler.
     */
    public $ob_level;
    /**
     * The path to the directory containing the
     * cli and html error view directories.
     *
     * @var string
     *
     * @deprecated 4.4.0 No longer used. Moved to BaseExceptionHandler.
     */
    protected $view_path;
    /**
     * Config for debug exceptions.
     *
     * @var ExceptionsConfig
     */
    protected $config;
    /**
     * The request.
     *
     * @var RequestInterface|null
     */
    protected $request;
    /**
     * The outgoing response.
     *
     * @var ResponseInterface
     */
    protected $response;
    private ?Throwable $exception_caught_by_exception_handler = null;
    public function __construct(Exceptions_Config $config)
    {
        // For backward compatibility
        $this->ob_level = ob_get_level();
        $this->view_path = rtrim($config->error_view_path, '\/ ') . DIRECTORY_SEPARATOR;
        $this->config = $config;
    }
    /**
     * Responsible for registering the error, exception and shutdown
     * handling of our application.
     *
     * @codeCoverageIgnore
     *
     * @return void
     */
    public function initialize()
    {
        set_exception_handler($this->exception_handler(...));
        set_error_handler($this->error_handler(...));
        register_shutdown_function([$this, 'shutdownHandler']);
    }
    /**
     * Catches any uncaught errors and exceptions, including most Fatal errors
     * (Yay PHP7!). Will log the error, display it if display_errors is on,
     * and fire an event that allows custom actions to be taken at this point.
     *
     * @return void
     */
    public function exception_handler(Throwable $exception)
    {
        $this->exception_caught_by_exception_handler = $exception;
        [$status_code, $exit_code] = $this->determine_codes($exception);
        $this->request = service('request');
        if ($this->config->log === true && !in_array($status_code, $this->config->ignore_codes, true)) {
            $uri = $this->request->get_path() === '' ? '/' : $this->request->get_path();
            $route_info = '[Method: ' . $this->request->get_method() . ', Route: ' . $uri . ']';
            log_message('critical', $exception::class . ": {message}\n{routeInfo}\nin {exFile} on line {exLine}.\n{trace}", [
                'message' => $exception->get_message(),
                'routeInfo' => $route_info,
                'exFile' => clean_path($exception->get_file()),
                // {file} refers to THIS file
                'exLine' => $exception->get_line(),
                // {line} refers to THIS line
                'trace' => render_backtrace($exception->get_trace()),
            ]);
            // Get the first exception.
            $last = $exception;
            while ($prev_exception = $last->get_previous()) {
                $last = $prev_exception;
                log_message('critical', '[Caused by] ' . $prev_exception::class . ": {message}\nin {exFile} on line {exLine}.\n{trace}", [
                    'message' => $prev_exception->get_message(),
                    'exFile' => clean_path($prev_exception->get_file()),
                    // {file} refers to THIS file
                    'exLine' => $prev_exception->get_line(),
                    // {line} refers to THIS line
                    'trace' => render_backtrace($prev_exception->get_trace()),
                ]);
            }
        }
        $this->response = service('response');
        if (method_exists($this->config, 'handler')) {
            // Use new ExceptionHandler
            $handler = $this->config->handler($status_code, $exception);
            $handler->handle($exception, $this->request, $this->response, $status_code, $exit_code);
            return;
        }
        // For backward compatibility
        if (!is_cli()) {
            try {
                $this->response->set_status_code($status_code);
            } catch (Http_Exception) {
                // Workaround for invalid HTTP status code.
                $status_code = 500;
                $this->response->set_status_code($status_code);
            }
            if (!headers_sent()) {
                header(sprintf('HTTP/%s %s %s', $this->request->get_protocol_version(), $this->response->get_status_code(), $this->response->get_reason_phrase()), true, $status_code);
            }
            if (!str_contains($this->request->get_header_line('accept'), 'text/html')) {
                $this->respond(ENVIRONMENT === 'development' ? $this->collect_vars($exception, $status_code) : '', $status_code)->send();
                exit($exit_code);
            }
        }
        $this->render($exception, $status_code);
        exit($exit_code);
    }
    /**
     * The callback to be registered to `set_error_handler()`.
     *
     * @return bool
     *
     * @throws ErrorException
     *
     * @codeCoverageIgnore
     */
    public function error_handler(int $severity, string $message, ?string $file = null, ?int $line = null)
    {
        if ($this->is_deprecation_error($severity)) {
            if ($this->is_session_sid_deprecation_error($message, $file, $line)) {
                return true;
            }
            if (!$this->config->log_deprecations || (bool) env('CODEIGNITER_SCREAM_DEPRECATIONS')) {
                throw new ErrorException($message, 0, $severity, $file, $line);
            }
            return $this->handle_deprecation_error($message, $file, $line);
        }
        if ((error_reporting() & $severity) !== 0) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
        return false;
        // return false to propagate the error to PHP standard error handler
    }
    /**
     * Handles session.sid_length and session.sid_bits_per_character deprecations
     * in PHP 8.4.
     */
    private function is_session_sid_deprecation_error(string $message, ?string $file = null, ?int $line = null): bool
    {
        if (PHP_VERSION_ID >= 80400 && str_contains($message, 'session.sid_')) {
            log_message(Log_Level::WARNING, '[DEPRECATED] {message} in {errFile} on line {errLine}.', ['message' => $message, 'errFile' => clean_path($file ?? ''), 'errLine' => $line ?? 0]);
            return true;
        }
        return false;
    }
    /**
     * Checks to see if any errors have happened during shutdown that
     * need to be caught and handle them.
     *
     * @codeCoverageIgnore
     *
     * @return void
     */
    public function shutdown_handler()
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }
        ['type' => $type, 'message' => $message, 'file' => $file, 'line' => $line] = $error;
        if ($this->exception_caught_by_exception_handler instanceof Throwable) {
            $message .= "\n【Previous Exception】\n" . $this->exception_caught_by_exception_handler::class . "\n" . $this->exception_caught_by_exception_handler->get_message() . "\n" . $this->exception_caught_by_exception_handler->get_trace_as_string();
        }
        if (in_array($type, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            $this->exception_handler(new ErrorException($message, 0, $type, $file, $line));
        }
    }
    /**
     * Determines the view to display based on the exception thrown,
     * whether an HTTP or CLI request, etc.
     *
     * @return string The path and filename of the view file to use
     *
     * @deprecated 4.4.0 No longer used. Moved to ExceptionHandler.
     */
    protected function determine_view(Throwable $exception, string $template_path): string
    {
        // Production environments should have a custom exception file.
        $view = 'production.php';
        $template_path = rtrim($template_path, '\/ ') . DIRECTORY_SEPARATOR;
        if (in_array(strtolower(ini_get('display_errors')), ['1', 'true', 'on', 'yes'], true)) {
            $view = 'error_exception.php';
        }
        // 404 Errors
        if ($exception instanceof Page_Not_Found_Exception) {
            return 'error_404.php';
        }
        // Allow for custom views based upon the status code
        if (is_file($template_path . 'error_' . $exception->get_code() . '.php')) {
            return 'error_' . $exception->get_code() . '.php';
        }
        return $view;
    }
    /**
     * Given an exception and status code will display the error to the client.
     *
     * @return void
     *
     * @deprecated 4.4.0 No longer used. Moved to BaseExceptionHandler.
     */
    protected function render(Throwable $exception, int $status_code)
    {
        // Determine possible directories of error views
        $path = $this->view_path;
        $alt_path = rtrim((new Paths())->view_directory, '\/ ') . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR;
        $path .= (is_cli() ? 'cli' : 'html') . DIRECTORY_SEPARATOR;
        $alt_path .= (is_cli() ? 'cli' : 'html') . DIRECTORY_SEPARATOR;
        // Determine the views
        $view = $this->determine_view($exception, $path);
        $alt_view = $this->determine_view($exception, $alt_path);
        // Check if the view exists
        if (is_file($path . $view)) {
            $view_file = $path . $view;
        } elseif (is_file($alt_path . $alt_view)) {
            $view_file = $alt_path . $alt_view;
        }
        if (!isset($view_file)) {
            echo 'The error view files were not found. Cannot render exception trace.';
            exit(1);
        }
        echo (function () use ($exception, $status_code, $view_file): string {
            $vars = $this->collect_vars($exception, $status_code);
            extract($vars, EXTR_SKIP);
            ob_start();
            include $view_file;
            return ob_get_clean();
        })();
    }
    /**
     * Gathers the variables that will be made available to the view.
     *
     * @deprecated 4.4.0 No longer used. Moved to BaseExceptionHandler.
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
     *
     * @param array $trace
     *
     * @return array
     *
     * @deprecated 4.4.0 No longer used. Moved to BaseExceptionHandler.
     */
    protected function mask_sensitive_data($trace, array $keys_to_mask, string $path = '')
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
     *
     * @deprecated 4.4.0 No longer used. Moved to BaseExceptionHandler.
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
     * Determines the HTTP status code and the exit status code for this request.
     */
    protected function determine_codes(Throwable $exception): array
    {
        $status_code = 500;
        $exit_status = EXIT_ERROR;
        if ($exception instanceof Http_Exception_Interface) {
            $status_code = $exception->get_code();
        }
        if ($exception instanceof Has_Exit_Code_Interface) {
            $exit_status = $exception->get_exit_code();
        }
        return [$status_code, $exit_status];
    }
    private function is_deprecation_error(int $error): bool
    {
        $deprecations = E_DEPRECATED | E_USER_DEPRECATED;
        return ($error & $deprecations) !== 0;
    }
    /**
     * @return true
     */
    private function handle_deprecation_error(string $message, ?string $file = null, ?int $line = null): bool
    {
        // Remove the trace of the error handler.
        $trace = array_slice(debug_backtrace(), 2);
        log_message($this->config->deprecation_log_level, "[DEPRECATED] {message} in {errFile} on line {errLine}.\n{trace}", ['message' => $message, 'errFile' => clean_path($file ?? ''), 'errLine' => $line ?? 0, 'trace' => render_backtrace($trace)]);
        return true;
    }
    // --------------------------------------------------------------------
    // Display Methods
    // --------------------------------------------------------------------
    /**
     * This makes nicer looking paths for the error output.
     *
     * @deprecated Use dedicated `clean_path()` function.
     */
    public static function clean_path(string $file): string
    {
        return match (true) {
            str_starts_with($file, APPPATH) => 'APPPATH' . DIRECTORY_SEPARATOR . substr($file, strlen(APPPATH)),
            str_starts_with($file, SYSTEMPATH) => 'SYSTEMPATH' . DIRECTORY_SEPARATOR . substr($file, strlen(SYSTEMPATH)),
            str_starts_with($file, FCPATH) => 'FCPATH' . DIRECTORY_SEPARATOR . substr($file, strlen(FCPATH)),
            defined('VENDORPATH') && str_starts_with($file, VENDORPATH) => 'VENDORPATH' . DIRECTORY_SEPARATOR . substr($file, strlen(VENDORPATH)),
            default => $file,
        };
    }
    /**
     * Describes memory usage in real-world units. Intended for use
     * with memory_get_usage, etc.
     *
     * @deprecated 4.4.0 No longer used. Moved to BaseExceptionHandler.
     */
    public static function describe_memory(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . 'B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 2) . 'KB';
        }
        return round($bytes / 1048576, 2) . 'MB';
    }
    /**
     * Creates a syntax-highlighted version of a PHP file.
     *
     * @return bool|string
     *
     * @deprecated 4.4.0 No longer used. Moved to BaseExceptionHandler.
     */
    public static function highlight_file(string $file, int $line_number, int $lines = 15)
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
        $source = str_replace('<br />', "\n", $source[1]);
        $source = explode("\n", str_replace("\r\n", "\n", $source));
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
        $spans = 1;
        foreach ($source as $n => $row) {
            $spans += substr_count($row, '<span') - substr_count($row, '</span');
            $row = str_replace(["\r", "\n"], ['', ''], $row);
            if ($n + $start + 1 === $line_number) {
                preg_match_all('#<[^>]+>#', $row, $tags);
                $out .= sprintf("<span class='line highlight'><span class='number'>{$format}</span> %s\n</span>%s", $n + $start + 1, strip_tags($row), implode('', $tags[0]));
            } else {
                $out .= sprintf('<span class="line"><span class="number">' . $format . '</span> %s', $n + $start + 1, $row) . "\n";
            }
        }
        if ($spans > 0) {
            $out .= str_repeat('</span>', $spans);
        }
        return '<pre><code>' . $out . '</code></pre>';
    }
}