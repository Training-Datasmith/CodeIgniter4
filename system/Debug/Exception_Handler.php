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

use Closure;
use Code_Igniter\API\Response_Trait;
use Code_Igniter\Exceptions\Page_Not_Found_Exception;
use Code_Igniter\HTTP\Cli_Request;
use Code_Igniter\HTTP\Exceptions\Http_Exception;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Config\Paths;
use Throwable;
/**
 * @see \CodeIgniter\Debug\ExceptionHandlerTest
 */
final class Exception_Handler extends Base_Exception_Handler implements Exception_Handler_Interface
{
    use Response_Trait;
    /**
     * ResponseTrait needs this.
     */
    private ?Request_Interface $request = null;
    /**
     * ResponseTrait needs this.
     */
    private ?Response_Interface $response = null;
    /**
     * Determines the correct way to display the error.
     *
     * @param CLIRequest|IncomingRequest $request
     */
    public function handle(Throwable $exception, Request_Interface $request, Response_Interface $response, int $status_code, int $exit_code): void
    {
        // ResponseTrait needs these properties.
        $this->request = $request;
        $this->response = $response;
        if ($request instanceof Incoming_Request) {
            try {
                $response->set_status_code($status_code);
            } catch (Http_Exception) {
                // Workaround for invalid HTTP status code.
                $status_code = 500;
                $response->set_status_code($status_code);
            }
            if (!headers_sent()) {
                header(sprintf('HTTP/%s %s %s', $request->get_protocol_version(), $response->get_status_code(), $response->get_reason_phrase()), true, $status_code);
            }
            // Handles non-HTML requests.
            if (!str_contains($request->get_header_line('accept'), 'text/html')) {
                // If display_errors is enabled, shows the error details.
                $data = $this->is_display_errors_enabled() ? $this->collect_vars($exception, $status_code) : '';
                // Sanitize data to remove non-JSON-serializable values (resources, closures)
                // before formatting for API responses (JSON, XML, etc.)
                if ($data !== '') {
                    $data = $this->sanitize_data($data);
                }
                $this->respond($data, $status_code)->send();
                if (ENVIRONMENT !== 'testing') {
                    // @codeCoverageIgnoreStart
                    exit($exit_code);
                    // @codeCoverageIgnoreEnd
                }
                return;
            }
        }
        // Determine possible directories of error views
        $add_path = ($request instanceof Incoming_Request ? 'html' : 'cli') . DIRECTORY_SEPARATOR;
        $path = $this->view_path . $add_path;
        $alt_path = rtrim((new Paths())->view_directory, '\/ ') . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . $add_path;
        // Determine the views
        $view = $this->determine_view($exception, $path, $status_code);
        $alt_view = $this->determine_view($exception, $alt_path, $status_code);
        // Check if the view exists
        $view_file = null;
        if (is_file($path . $view)) {
            $view_file = $path . $view;
        } elseif (is_file($alt_path . $alt_view)) {
            $view_file = $alt_path . $alt_view;
        }
        // Displays the HTML or CLI error code.
        $this->render($exception, $status_code, $view_file);
        if (ENVIRONMENT !== 'testing') {
            // @codeCoverageIgnoreStart
            exit($exit_code);
            // @codeCoverageIgnoreEnd
        }
    }
    /**
     * Determines the view to display based on the exception thrown, HTTP status
     * code, whether an HTTP or CLI request, etc.
     *
     * @return string The filename of the view file to use
     */
    protected function determine_view(Throwable $exception, string $template_path, int $status_code = 500): string
    {
        // Production environments should have a custom exception file.
        $view = 'production.php';
        if ($this->is_display_errors_enabled()) {
            // If display_errors is enabled, shows the error details.
            $view = 'error_exception.php';
        }
        // 404 Errors
        if ($exception instanceof Page_Not_Found_Exception) {
            return 'error_404.php';
        }
        $template_path = rtrim($template_path, '\/ ') . DIRECTORY_SEPARATOR;
        // Allow for custom views based upon the status code
        if (is_file($template_path . 'error_' . $status_code . '.php')) {
            return 'error_' . $status_code . '.php';
        }
        return $view;
    }
    private function is_display_errors_enabled(): bool
    {
        return in_array(strtolower(ini_get('display_errors')), ['1', 'true', 'on', 'yes'], true);
    }
    /**
     * Sanitizes data to remove non-JSON-serializable values like resources and closures.
     * This is necessary for API responses that need to be JSON/XML encoded.
     *
     * @param array<int, bool> $seen Used internally to prevent infinite recursion
     */
    private function sanitize_data(mixed $data, array &$seen = []): mixed
    {
        $type = gettype($data);
        switch ($type) {
            case 'resource':
            case 'resource (closed)':
                return '[Resource #' . (int) $data . ']';
            case 'array':
                $result = [];
                foreach ($data as $key => $value) {
                    $result[$key] = $this->sanitize_data($value, $seen);
                }
                return $result;
            case 'object':
                $oid = spl_object_id($data);
                if (isset($seen[$oid])) {
                    return '[' . $data::class . ' Object *RECURSION*]';
                }
                $seen[$oid] = true;
                if ($data instanceof Closure) {
                    return '[Closure]';
                }
                $result = [];
                foreach ((array) $data as $key => $value) {
                    $clean_key = preg_replace('/^\x00.*\x00/', '', (string) $key);
                    $result[$clean_key] = $this->sanitize_data($value, $seen);
                }
                return $result;
            default:
                return $data;
        }
    }
}