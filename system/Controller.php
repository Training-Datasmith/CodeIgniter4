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
namespace Code_Igniter;

use Code_Igniter\HTTP\Cli_Request;
use Code_Igniter\HTTP\Exceptions\Http_Exception;
use Code_Igniter\HTTP\Exceptions\Redirect_Exception;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Code_Igniter\Validation\Exceptions\Validation_Exception;
use Code_Igniter\Validation\Validation_Interface;
use Config\Validation;
use Psr\Log\Logger_Interface;
/**
 * @see \CodeIgniter\ControllerTest
 */
class Controller
{
    /**
     * Helpers that will be automatically loaded on class instantiation.
     *
     * @var list<string>
     */
    protected $helpers = [];
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;
    /**
     * Instance of the main response object.
     *
     * @var ResponseInterface
     */
    protected $response;
    /**
     * Instance of logger to use.
     *
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * Should enforce HTTPS access for all methods in this controller.
     *
     * @var int Number of seconds to set HSTS header
     */
    protected $force_https = 0;
    /**
     * Once validation has been run, will hold the Validation instance.
     *
     * @var ValidationInterface|null
     */
    protected $validator;
    /**
     * Dependency-injection entry point called by the framework after instantiation.
     *
     * Not a true constructor — CodeIgniter instantiates controllers without
     * constructor arguments, so this method acts as the injection point for the
     * three core dependencies. Override `initController()` (or call parent) in
     * subclasses; do NOT override the constructor.
     *
     * If `$force_https` is greater than zero on the subclass, an HTTPS redirect
     * is issued automatically for non-HTTPS requests before any action logic runs.
     *
     * @param Request_Interface $request The active HTTP or CLI request object.
     * @param Response_Interface $response The outgoing response object.
     * @param Logger_Interface $logger PSR-3 compatible logger for this controller.
     * @return void
     * @throws \CodeIgniter\HTTP\Exceptions\HTTPException On an unrecoverable request error.
     * @throws \CodeIgniter\HTTP\Exceptions\RedirectException When an HTTPS redirect is issued.
     * @since 4.0.0
     */
    public function init_controller(Request_Interface $request, Response_Interface $response, Logger_Interface $logger): void
    {
        $this->request = $request;
        $this->response = $response;
        $this->logger = $logger;
        if ($this->force_https > 0) {
            $this->force_https($this->force_https);
        }
        // Autoload helper files.
        helper($this->helpers);
    }
    /**
     * A convenience method to use when you need to ensure that a single
     * method is reached only via HTTPS. If it isn't, then a redirect
     * will happen back to this method and HSTS header will be sent
     * to have modern browsers transform requests automatically.
     *
     * @param int $duration The number of seconds this link should be
     *                      considered secure for. Only with HSTS header.
     *                      Default value is 1 year.
     *
     * @return void
     *
     * @throws HTTPException|RedirectException
     */
    protected function force_https(int $duration = 31536000)
    {
        force_https($duration, $this->request, $this->response);
    }
    /**
     * How long to cache the current page for.
     *
     * @params int $time time to live in seconds.
     *
     * @return void
     */
    protected function cache_page(int $time)
    {
        service('responsecache')->set_ttl($time);
    }
    /**
     * Validates the current request's POST/JSON body data against the given rules.
     *
     * After calling this method, `$this->validator` holds the populated Validation
     * instance so you can retrieve error messages via `$this->validator->getErrors()`.
     *
     * @param array<string, array<string>|string>|string $rules Inline rule array keyed by field
     *   name, or a string referencing a named rule group defined in `app/Config/Validation.php`.
     * @param array<string, string> $messages Custom error message overrides keyed by
     *   `'field.rule'` (e.g. `'email.required' => 'Email is required'`).
     * @return bool `true` when all rules pass; `false` when any rule fails.
     * @throws \CodeIgniter\Validation\Exceptions\ValidationException When the named rule group
     *   does not exist in `Config\Validation`.
     * @see \CodeIgniter\Validation\ValidationInterface::getErrors() To retrieve failure messages.
     * @since 4.0.0
     */
    protected function validate(array|string $rules, array $messages = []): bool
    {
        $this->set_validator($rules, $messages);
        return $this->validator->with_request($this->request)->run();
    }
    /**
     * Validates an arbitrary array of data against the given rules.
     *
     * Unlike `validate()`, this method accepts any data array rather than reading
     * from the request — useful for validating API payloads already decoded into
     * an array, or for unit-testing validation logic without a real request.
     *
     * @param array<string, mixed> $data The data array to validate (key → value pairs).
     * @param array<string, array<string>|string>|string $rules Inline rule array or named
     *   group from `app/Config/Validation.php`.
     * @param array<string, string> $messages Custom error message overrides.
     * @param string|null $db_group Database group name used by `is_unique` / `is_not_unique`
     *   rules that need a DB connection. `null` uses the default group.
     * @return bool `true` when all rules pass; `false` otherwise.
     * @since 4.0.0
     */
    protected function validate_data(array $data, array|string $rules, array $messages = [], ?string $db_group = null): bool
    {
        $this->set_validator($rules, $messages);
        return $this->validator->run($data, null, $db_group);
    }
    /**
     * @param array|string $rules
     */
    private function set_validator($rules, array $messages): void
    {
        $this->validator = service('validation');
        // If you replace the $rules array with the name of the group
        if (is_string($rules)) {
            $validation = config(Validation::class);
            // If the rule wasn't found in the \Config\Validation, we
            // should throw an exception so the developer can find it.
            if (!isset($validation->{$rules})) {
                throw Validation_Exception::for_rule_not_found($rules);
            }
            // If no error message is defined, use the error message in the Config\Validation file
            if ($messages === []) {
                $error_name = $rules . '_errors';
                $messages = $validation->{$error_name} ?? [];
            }
            $rules = $validation->{$rules};
        }
        $this->validator->set_rules($rules, $messages);
    }
}