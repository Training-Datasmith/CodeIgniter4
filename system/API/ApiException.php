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

use Code_Igniter\Exceptions\Framework_Exception;
/**
 * Custom exception for API-related errors.
 */
final class Api_Exception extends Framework_Exception
{
    /**
     * Thrown when the fields requested in a URL are not valid.
     */
    public static function for_invalid_fields(string $field): self
    {
        return new self(lang('Api.invalidFields', [$field]));
    }
    /**
     * Thrown when the includes requested in a URL are not valid.
     */
    public static function for_invalid_includes(string $include): self
    {
        return new self(lang('Api.invalidIncludes', [$include]));
    }
    /**
     * Thrown when an include is requested, but the method to handle it
     * does not exist on the model.
     */
    public static function for_missing_include(string $include): self
    {
        return new self(lang('Api.missingInclude', [$include]));
    }
    /**
     * Thrown when a transformer class cannot be found.
     */
    public static function for_transformer_not_found(string $transformer_class): self
    {
        return new self(lang('Api.transformerNotFound', [$transformer_class]));
    }
    /**
     * Thrown when a transformer class does not implement TransformerInterface.
     */
    public static function for_invalid_transformer(string $transformer_class): self
    {
        return new self(lang('Api.invalidTransformer', [$transformer_class]));
    }
}