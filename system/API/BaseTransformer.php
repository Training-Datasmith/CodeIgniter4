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

use Code_Igniter\HTTP\Incoming_Request;
use InvalidArgumentException;
/**
 * Base class for transforming resources into arrays.
 * Fulfills common functionality of the TransformerInterface,
 * and provides helper methods for conditional inclusion/exclusion of values.
 *
 * Supports the following query variables from the request:
 * - fields: Comma-separated list of fields to include in the response
 *      (e.g., ?fields=id,name,email)
 *      If not provided, all fields from toArray() are included.
 * - include: Comma-separated list of related resources to include
 *      (e.g., ?include=posts,comments)
 *      This looks for methods named `include{Resource}()` on the transformer,
 *      and calls them to get the related data, which are added as a new key to the output.
 *
 * Example:
 *
 * class UserTransformer extends BaseTransformer
 * {
 *    public function toArray(mixed $resource): array
 *    {
 *      return [
 *          'id' => $resource['id'],
 *          'name' => $resource['name'],
 *          'email' => $resource['email'],
 *          'created_at' => $resource['created_at'],
 *          'updated_at' => $resource['updated_at'],
 *      ];
 *    }
 *
 *   protected function includePosts(): array
 *   {
 *       $posts = model('PostModel')->where('user_id', $this->resource['id'])->findAll();
 *       return (new PostTransformer())->transformMany($posts);
 *   }
 * }
 */
abstract class Base_Transformer implements Transformer_Interface
{
    /**
     * @var list<string>|null
     */
    private ?array $fields = null;
    /**
     * @var list<string>|null
     */
    private ?array $includes = null;
    protected mixed $resource = null;
    public function __construct(private ?Incoming_Request $request = null)
    {
        $this->request = $request ?? request();
        $fields = $this->request->get_get('fields');
        $this->fields = is_string($fields) ? array_map(trim(...), explode(',', $fields)) : $fields;
        $includes = $this->request->get_get('include');
        $this->includes = is_string($includes) ? array_map(trim(...), explode(',', $includes)) : $includes;
    }
    /**
     * Converts the resource to an array representation.
     * This is overridden by child classes to define the
     * API-safe resource representation.
     *
     * @param mixed $resource The resource being transformed
     */
    abstract public function to_array(mixed $resource): array;
    /**
     * Transforms the given resource into an array using
     * the $this->toArray().
     */
    public function transform(array|object|null $resource = null): array
    {
        // Store the resource so include methods can access it
        $this->resource = $resource;
        if ($resource === null) {
            $data = $this->to_array(null);
        } elseif (is_object($resource) && method_exists($resource, 'toArray')) {
            $data = $this->to_array($resource->to_array());
        } else {
            $data = $this->to_array((array) $resource);
        }
        $data = $this->limit_fields($data);
        return $this->insert_includes($data);
    }
    /**
     * Transforms a collection of resources using $this->transform() on each item.
     *
     * If the request's 'fields' query variable is set, only those fields will be included
     * in the transformed output.
     */
    public function transform_many(array $resources): array
    {
        return array_map($this->transform(...), $resources);
    }
    /**
     * Define which fields can be requested via the 'fields' query parameter.
     * Override in child classes to restrict available fields.
     * Return null to allow all fields from toArray().
     *
     * @return list<string>|null
     */
    protected function get_allowed_fields(): ?array
    {
        return null;
    }
    /**
     * Define which related resources can be included via the 'include' query parameter.
     * Override in child classes to restrict available includes.
     * Return null to allow all includes that have corresponding methods.
     * Return an empty array to disable all includes.
     *
     * @return list<string>|null
     */
    protected function get_allowed_includes(): ?array
    {
        return null;
    }
    /**
     * Limits the given data array to only the fields specified
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    private function limit_fields(array $data): array
    {
        if ($this->fields === null || $this->fields === []) {
            return $data;
        }
        $allowed_fields = $this->get_allowed_fields();
        // If whitelist is defined, validate against it
        if ($allowed_fields !== null) {
            $invalid_fields = array_diff($this->fields, $allowed_fields);
            if ($invalid_fields !== []) {
                throw Api_Exception::for_invalid_fields(implode(', ', $invalid_fields));
            }
        }
        return array_intersect_key($data, array_flip($this->fields));
    }
    /**
     * Checks the request for 'include' query variable, and if present,
     * calls the corresponding include{Resource} methods to add related data.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function insert_includes(array $data): array
    {
        if ($this->includes === null) {
            return $data;
        }
        $allowed_includes = $this->get_allowed_includes();
        if ($allowed_includes === []) {
            return $data;
            // No includes allowed
        }
        // If whitelist is defined, filter the requested includes
        if ($allowed_includes !== null) {
            $invalid_includes = array_diff($this->includes, $allowed_includes);
            if ($invalid_includes !== []) {
                throw Api_Exception::for_invalid_includes(implode(', ', $invalid_includes));
            }
        }
        foreach ($this->includes as $include) {
            $method = 'include' . ucfirst($include);
            if (method_exists($this, $method)) {
                $data[$include] = $this->{$method}();
            } else {
                throw Api_Exception::for_missing_include($include);
            }
        }
        return $data;
    }
}