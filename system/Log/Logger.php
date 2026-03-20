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
namespace Code_Igniter\Log;

use Code_Igniter\Exceptions\RuntimeException;
use Code_Igniter\Log\Exceptions\Log_Exception;
use Code_Igniter\Log\Handlers\Handler_Interface;
use Psr\Log\Logger_Interface;
use Stringable;
use Throwable;
/**
 * The CodeIgntier Logger
 *
 * The message MUST be a string or object implementing __toString().
 *
 * The message MAY contain placeholders in the form: {foo} where foo
 * will be replaced by the context data in key "foo".
 *
 * The context array can contain arbitrary data, the only assumption that
 * can be made by implementors is that if an Exception instance is given
 * to produce a stack trace, it MUST be in a key named "exception".
 *
 * @see \CodeIgniter\Log\LoggerTest
 */
class Logger implements Logger_Interface
{
    /**
     * Used by the logThreshold Config setting to define
     * which errors to show.
     *
     * @var array<string, int>
     */
    protected $log_levels = ['emergency' => 1, 'alert' => 2, 'critical' => 3, 'error' => 4, 'warning' => 5, 'notice' => 6, 'info' => 7, 'debug' => 8];
    /**
     * Array of levels to be logged. The rest will be ignored.
     *
     * Set in app/Config/Logger.php
     *
     * @var list<string>
     */
    protected $loggable_levels = [];
    /**
     * File permissions
     *
     * @var int
     */
    protected $file_permissions = 0644;
    /**
     * Format of the timestamp for log files.
     *
     * @var string
     */
    protected $date_format = 'Y-m-d H:i:s';
    /**
     * Filename Extension
     *
     * @var string
     */
    protected $file_ext;
    /**
     * Caches instances of the handlers.
     *
     * @var array<class-string<HandlerInterface>, HandlerInterface>
     */
    protected $handlers = [];
    /**
     * Holds the configuration for each handler.
     * The key is the handler's class name. The
     * value is an associative array of configuration
     * items.
     *
     * @var array<class-string<HandlerInterface>, array<string, int|list<string>|string>>
     */
    protected $handler_config = [];
    /**
     * Caches logging calls for debugbar.
     *
     * @var list<array{level: string, msg: string}>
     */
    public $log_cache;
    /**
     * Should we cache our logged items?
     *
     * @var bool
     */
    protected $cache_logs = false;
    /**
     * Constructor.
     *
     * @param \Config\Logger $config
     *
     * @throws RuntimeException
     */
    public function __construct($config, bool $debug = CI_DEBUG)
    {
        $loggable_levels = is_array($config->threshold) ? $config->threshold : range(1, (int) $config->threshold);
        // Now convert loggable levels to strings.
        // We only use numbers to make the threshold setting convenient for users.
        foreach ($loggable_levels as $level) {
            /** @var false|string $stringLevel */
            $string_level = array_search($level, $this->log_levels, true);
            if ($string_level === false) {
                continue;
            }
            $this->loggable_levels[] = $string_level;
        }
        if (isset($config->date_format)) {
            $this->date_format = $config->date_format;
        }
        if ($config->handlers === []) {
            throw Log_Exception::for_no_handlers('LoggerConfig');
        }
        // Save the handler configuration for later.
        // Instances will be created on demand.
        $this->handler_config = $config->handlers;
        $this->cache_logs = $debug;
        if ($this->cache_logs) {
            $this->log_cache = [];
        }
    }
    /**
     * System is unusable.
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }
    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }
    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }
    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }
    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }
    /**
     * Normal but significant events.
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }
    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }
    /**
     * Detailed debug information.
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (is_numeric($level)) {
            $level = array_search((int) $level, $this->log_levels, true);
        }
        if (!array_key_exists($level, $this->log_levels)) {
            throw Log_Exception::for_invalid_log_level($level);
        }
        if (!in_array($level, $this->loggable_levels, true)) {
            return;
        }
        $message = $this->interpolate($message, $context);
        if ($this->cache_logs) {
            $this->log_cache[] = ['level' => $level, 'msg' => $message];
        }
        foreach ($this->handler_config as $class_name => $config) {
            if (!array_key_exists($class_name, $this->handlers)) {
                $this->handlers[$class_name] = new $class_name($config);
            }
            $handler = $this->handlers[$class_name];
            if (!$handler->can_handle($level)) {
                continue;
            }
            // If the handler returns false, then we don't execute any other handlers.
            if (!$handler->set_date_format($this->date_format)->handle($level, $message)) {
                break;
            }
        }
    }
    /**
     * Replaces any placeholders in the message with variables
     * from the context, as well as a few special items like:
     *
     * {session_vars}
     * {post_vars}
     * {get_vars}
     * {env}
     * {env:foo}
     * {file}
     * {line}
     *
     * @param string|Stringable    $message
     * @param array<string, mixed> $context
     *
     * @return string
     */
    protected function interpolate($message, array $context = [])
    {
        if (!is_string($message)) {
            return print_r($message, true);
        }
        $replace = [];
        foreach ($context as $key => $val) {
            // Verify that the 'exception' key is actually an exception
            // or error, both of which implement the 'Throwable' interface.
            if ($key === 'exception' && $val instanceof Throwable) {
                $val = $val->get_message() . ' ' . clean_path($val->get_file()) . ':' . $val->get_line();
            }
            // todo - sanitize input before writing to file?
            $replace['{' . $key . '}'] = $val;
        }
        $replace['{post_vars}'] = '$_POST: ' . print_r(service('superglobals')->get_post_array(), true);
        $replace['{get_vars}'] = '$_GET: ' . print_r(service('superglobals')->get_get_array(), true);
        $replace['{env}'] = ENVIRONMENT;
        // Allow us to log the file/line that we are logging from
        if (str_contains($message, '{file}') || str_contains($message, '{line}')) {
            [$file, $line] = $this->determine_file();
            $replace['{file}'] = $file;
            $replace['{line}'] = $line;
        }
        // Match up environment variables in {env:foo} tags.
        if (str_contains($message, 'env:')) {
            preg_match('/env:[^}]+/', $message, $matches);
            foreach ($matches as $str) {
                $key = str_replace('env:', '', $str);
                $replace["{{$str}}"] = $_ENV[$key] ?? 'n/a';
            }
        }
        if (isset($_SESSION)) {
            $replace['{session_vars}'] = '$_SESSION: ' . print_r($_SESSION, true);
        }
        return strtr($message, $replace);
    }
    /**
     * Determines the file and line that the logging call
     * was made from by analyzing the backtrace.
     * Find the earliest stack frame that is part of our logging system.
     *
     * @return array{string, int|string}
     */
    public function determine_file(): array
    {
        $log_functions = ['log_message', 'log', 'error', 'debug', 'info', 'warning', 'critical', 'emergency', 'alert', 'notice'];
        $trace = debug_backtrace(0);
        $stack_frames = array_reverse($trace);
        foreach ($stack_frames as $frame) {
            if (in_array($frame['function'], $log_functions, true)) {
                $file = isset($frame['file']) ? clean_path($frame['file']) : 'unknown';
                $line = $frame['line'] ?? 'unknown';
                return [$file, $line];
            }
        }
        return ['unknown', 'unknown'];
    }
}