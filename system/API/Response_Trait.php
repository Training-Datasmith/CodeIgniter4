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
namespace Code_Igniter\API;

use Code_Igniter\Database\Base_Builder;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Format\Format;
use Code_Igniter\Format\Formatter_Interface;
use Code_Igniter\HTTP\Cli_Request;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Response_Interface;
use Code_Igniter\HTTP\URI;
use Code_Igniter\Model;
use Throwable;
/**
 * Provides common, more readable, methods to provide
 * consistent HTTP responses under a variety of common
 * situations when working as an API.
 *
 * @property CLIRequest|IncomingRequest $request
 * @property ResponseInterface          $response
 * @property bool                       $stringAsHtml Whether to treat string data as HTML in JSON response.
 *                                                    Setting `true` is only for backward compatibility.
 */
trait Response_Trait
{
    /**
     * Allows child classes to override the
     * status code that is used in their API.
     *
     * @var array<string, int>
     */
    protected $codes = ['created' => 201, 'deleted' => 200, 'updated' => 200, 'no_content' => 204, 'invalid_request' => 400, 'unsupported_response_type' => 400, 'invalid_scope' => 400, 'temporarily_unavailable' => 400, 'invalid_grant' => 400, 'invalid_credentials' => 400, 'invalid_refresh' => 400, 'no_data' => 400, 'invalid_data' => 400, 'access_denied' => 401, 'unauthorized' => 401, 'invalid_client' => 401, 'forbidden' => 403, 'resource_not_found' => 404, 'not_acceptable' => 406, 'resource_exists' => 409, 'conflict' => 409, 'resource_gone' => 410, 'payload_too_large' => 413, 'unsupported_media_type' => 415, 'too_many_requests' => 429, 'server_error' => 500, 'unsupported_grant_type' => 501, 'not_implemented' => 501];
    /**
     * How to format the response data.
     * Either 'json' or 'xml'. If null is set, it will be determined through
     * content negotiation.
     *
     * @var 'html'|'json'|'xml'|null
     */
    protected $format = 'json';
    /**
     * Current Formatter instance. This is usually set by ResponseTrait::format
     *
     * @var FormatterInterface|null
     */
    protected $formatter;
    /**
     * Provides a single, simple method to return an API response, formatted
     * to match the requested format, with proper content-type and status code.
     *
     * @param array<string, mixed>|string|null $data
     *
     * @return ResponseInterface
     */
    protected function respond($data = null, ?int $status = null, string $message = '')
    {
        if ($data === null && $status === null) {
            $status = 404;
            $output = null;
            $this->format($data);
        } elseif ($data === null && is_numeric($status)) {
            $output = null;
            $this->format($data);
        } else {
            $status ??= 200;
            $output = $this->format($data);
        }
        if ($output !== null) {
            if ($this->format === 'json') {
                return $this->response->set_json($output)->set_status_code($status, $message);
            }
            if ($this->format === 'xml') {
                return $this->response->set_xml($output)->set_status_code($status, $message);
            }
        }
        return $this->response->set_body($output)->set_status_code($status, $message);
    }
    /**
     * Used for generic failures that no custom methods exist for.
     *
     * @param array<array-key, string>|string $messages
     * @param int                             $status   HTTP status code
     * @param string|null                     $code     Custom, API-specific, error code
     *
     * @return ResponseInterface
     */
    protected function fail($messages, int $status = 400, ?string $code = null, string $custom_message = '')
    {
        if (!is_array($messages)) {
            $messages = ['error' => $messages];
        }
        $response = ['status' => $status, 'error' => $code ?? $status, 'messages' => $messages];
        return $this->respond($response, $status, $custom_message);
    }
    // --------------------------------------------------------------------
    // Response Helpers
    // --------------------------------------------------------------------
    /**
     * Used after successfully creating a new resource.
     *
     * @param array<string, mixed>|string|null $data
     *
     * @return ResponseInterface
     */
    protected function respond_created($data = null, string $message = '')
    {
        return $this->respond($data, $this->codes['created'], $message);
    }
    /**
     * Used after a resource has been successfully deleted.
     *
     * @param array<string, mixed>|string|null $data
     *
     * @return ResponseInterface
     */
    protected function respond_deleted($data = null, string $message = '')
    {
        return $this->respond($data, $this->codes['deleted'], $message);
    }
    /**
     * Used after a resource has been successfully updated.
     *
     * @param array<string, mixed>|string|null $data
     *
     * @return ResponseInterface
     */
    protected function respond_updated($data = null, string $message = '')
    {
        return $this->respond($data, $this->codes['updated'], $message);
    }
    /**
     * Used after a command has been successfully executed but there is no
     * meaningful reply to send back to the client.
     *
     * @return ResponseInterface
     */
    protected function respond_no_content(string $message = 'No Content')
    {
        return $this->respond(null, $this->codes['no_content'], $message);
    }
    /**
     * Used when the client is either didn't send authorization information,
     * or had bad authorization credentials. User is encouraged to try again
     * with the proper information.
     *
     * @return ResponseInterface
     */
    protected function fail_unauthorized(string $description = 'Unauthorized', ?string $code = null, string $message = '')
    {
        return $this->fail($description, $this->codes['unauthorized'], $code, $message);
    }
    /**
     * Used when access is always denied to this resource and no amount
     * of trying again will help.
     *
     * @return ResponseInterface
     */
    protected function fail_forbidden(string $description = 'Forbidden', ?string $code = null, string $message = '')
    {
        return $this->fail($description, $this->codes['forbidden'], $code, $message);
    }
    /**
     * Used when a specified resource cannot be found.
     *
     * @return ResponseInterface
     */
    protected function fail_not_found(string $description = 'Not Found', ?string $code = null, string $message = '')
    {
        return $this->fail($description, $this->codes['resource_not_found'], $code, $message);
    }
    /**
     * Used when the data provided by the client cannot be validated on one or more fields.
     *
     * @param array<array-key, string>|string $errors
     *
     * @return ResponseInterface
     */
    protected function fail_validation_errors($errors, ?string $code = null, string $message = '')
    {
        return $this->fail($errors, $this->codes['invalid_data'], $code, $message);
    }
    /**
     * Use when trying to create a new resource and it already exists.
     *
     * @return ResponseInterface
     */
    protected function fail_resource_exists(string $description = 'Conflict', ?string $code = null, string $message = '')
    {
        return $this->fail($description, $this->codes['resource_exists'], $code, $message);
    }
    /**
     * Use when a resource was previously deleted. This is different than
     * Not Found, because here we know the data previously existed, but is now gone,
     * where Not Found means we simply cannot find any information about it.
     *
     * @return ResponseInterface
     */
    protected function fail_resource_gone(string $description = 'Gone', ?string $code = null, string $message = '')
    {
        return $this->fail($description, $this->codes['resource_gone'], $code, $message);
    }
    /**
     * Used when the user has made too many requests for the resource recently.
     *
     * @return ResponseInterface
     */
    protected function fail_too_many_requests(string $description = 'Too Many Requests', ?string $code = null, string $message = '')
    {
        return $this->fail($description, $this->codes['too_many_requests'], $code, $message);
    }
    /**
     * Used when there is a server error.
     *
     * @param string      $description The error message to show the user.
     * @param string|null $code        A custom, API-specific, error code.
     * @param string      $message     A custom "reason" message to return.
     */
    protected function fail_server_error(string $description = 'Internal Server Error', ?string $code = null, string $message = ''): Response_Interface
    {
        return $this->fail($description, $this->codes['server_error'], $code, $message);
    }
    // --------------------------------------------------------------------
    // Utility Methods
    // --------------------------------------------------------------------
    /**
     * Handles formatting a response. Currently, makes some heavy assumptions
     * and needs updating! :)
     *
     * @param array<string, mixed>|string|null $data
     *
     * @return string|null
     */
    protected function format($data = null)
    {
        /** @var Format $format */
        $format = service('format');
        $mime = $this->format === null ? $format->get_config()->supported_response_formats[0] : "application/{$this->format}";
        // Determine correct response type through content negotiation if not explicitly declared
        if (!in_array($this->format, ['json', 'xml'], true) && $this->request instanceof Incoming_Request) {
            $mime = $this->request->negotiate('media', $format->get_config()->supported_response_formats, false);
        }
        $this->response->set_content_type($mime);
        // if we don't have a formatter, make one
        $this->formatter ??= $format->get_formatter($mime);
        $as_html = property_exists($this, 'stringAsHtml') ? $this->string_as_html : false;
        if ($mime === 'application/json' && $as_html && is_string($data) || $mime !== 'application/json' && is_string($data)) {
            // The content type should be text/... and not application/...
            $content_type = $this->response->get_header_line('Content-Type');
            $content_type = str_replace('application/json', 'text/html', $content_type);
            $content_type = str_replace('application/', 'text/', $content_type);
            $this->response->set_content_type($content_type);
            $this->format = 'html';
            return $data;
        }
        if ($mime !== 'application/json') {
            // Recursively convert objects into associative arrays
            // Conversion not required for JSONFormatter
            /** @var array<string, mixed>|string|null $data */
            $data = json_decode(json_encode($data), true);
        }
        return $this->formatter->format($data);
    }
    /**
     * Sets the format the response should be in.
     *
     * @param 'json'|'xml' $format Response format
     *
     * @return $this
     */
    protected function set_response_format(?string $format = null)
    {
        $this->format = $format === null ? null : strtolower($format);
        return $this;
    }
    // --------------------------------------------------------------------
    // Pagination Methods
    // --------------------------------------------------------------------
    /**
     * Paginates the given model or query builder and returns
     * an array containing the paginated results along with
     * metadata such as total items, total pages, current page,
     * and items per page.
     *
     * The result would be in the following format:
     * [
     *   'data' => [...],
     *   'meta' => [
     *       'page' => 1,
     *       'perPage' => 20,
     *       'total' => 100,
     *       'totalPages' => 5,
     *   ],
     *   'links' => [
     *       'self' => '/api/items?page=1&perPage=20',
     *       'first' => '/api/items?page=1&perPage=20',
     *       'last' => '/api/items?page=5&perPage=20',
     *       'prev' => null,
     *       'next' => '/api/items?page=2&perPage=20',
     *   ]
     * ]
     *
     * @param class-string<TransformerInterface>|null $transformWith
     */
    protected function paginate(Base_Builder|Model $resource, int $per_page = 20, ?string $transform_with = null): Response_Interface
    {
        try {
            assert($this->request instanceof Incoming_Request);
            $page = max(1, (int) ($this->request->get_get('page') ?? 1));
            // If using a Model we can use its built-in paginate method
            if ($resource instanceof Model) {
                $data = $resource->paginate($per_page, 'default', $page);
                $pager = $resource->pager;
                $meta = ['page' => $pager->get_current_page(), 'perPage' => $pager->get_per_page(), 'total' => $pager->get_total(), 'totalPages' => $pager->get_page_count()];
            } else {
                // Query Builder, we need to handle pagination manually
                $offset = ($page - 1) * $per_page;
                $total = (clone $resource)->count_all_results();
                $data = $resource->limit($per_page, $offset)->get()->get_result_array();
                $meta = ['page' => $page, 'perPage' => $per_page, 'total' => $total, 'totalPages' => (int) ceil($total / $per_page)];
            }
            // Transform data if a transformer is provided
            if ($transform_with !== null) {
                if (!class_exists($transform_with)) {
                    throw Api_Exception::for_transformer_not_found($transform_with);
                }
                $transformer = new $transform_with($this->request);
                if (!$transformer instanceof Transformer_Interface) {
                    throw Api_Exception::for_invalid_transformer($transform_with);
                }
                $data = $transformer->transform_many($data);
            }
            $links = $this->build_links($meta);
            $this->response->set_header('Link', $this->link_header($links));
            $this->response->set_header('X-Total-Count', (string) $meta['total']);
            return $this->respond(['data' => $data, 'meta' => $meta, 'links' => $links]);
        } catch (Api_Exception $e) {
            // Re-throw ApiExceptions so they can be handled by the caller
            throw $e;
        } catch (Database_Exception $e) {
            log_message('error', lang('RESTful.cannotPaginate') . ' ' . $e->get_message());
            return $this->fail_server_error(lang('RESTful.cannotPaginate'));
        } catch (Throwable $e) {
            log_message('error', lang('RESTful.paginateError') . ' ' . $e->get_message());
            return $this->fail_server_error(lang('RESTful.paginateError'));
        }
    }
    /**
     * Builds pagination links based on the current request URI and pagination metadata.
     *
     * @param array<string, int> $meta Pagination metadata (page, perPage, total, totalPages)
     *
     * @return array<string, string|null> Array of pagination links with relations as keys
     */
    private function build_links(array $meta): array
    {
        assert($this->request instanceof Incoming_Request);
        /** @var URI $uri */
        $uri = current_url(true);
        $query = $this->request->get_get();
        $set = static function ($page) use ($uri, $query, $meta): string {
            $params = $query;
            $params['page'] = $page;
            // Ensure perPage is in the links if it's not default
            if (!isset($params['perPage']) && $meta['perPage'] !== 20) {
                $params['perPage'] = $meta['perPage'];
            }
            return (string) (new URI((string) $uri))->set_query(http_build_query($params));
        };
        $total_pages = max(1, (int) $meta['totalPages']);
        $page = (int) $meta['page'];
        return ['self' => $set($page), 'first' => $set(1), 'last' => $set($total_pages), 'prev' => $page > 1 ? $set($page - 1) : null, 'next' => $page < $total_pages ? $set($page + 1) : null];
    }
    /**
     * Formats the pagination links into a single Link header string
     * for middleware/machine use.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Link
     * @see https://datatracker.ietf.org/doc/html/rfc8288
     *
     * @param array<string, string|null> $links Pagination links with relations as keys
     *
     * @return string Formatted Link header value
     */
    private function link_header(array $links): string
    {
        $parts = [];
        foreach (['self', 'first', 'prev', 'next', 'last'] as $rel) {
            if ($links[$rel] !== null && $links[$rel] !== '') {
                $parts[] = "<{$links[$rel]}>; rel=\"{$rel}\"";
            }
        }
        return implode(', ', $parts);
    }
}