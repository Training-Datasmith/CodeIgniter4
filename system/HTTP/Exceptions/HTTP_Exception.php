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
namespace Code_Igniter\HTTP\Exceptions;

use Code_Igniter\Exceptions\Framework_Exception;
/**
 * Things that can go wrong with HTTP
 */
class Http_Exception extends Framework_Exception implements Exception_Interface
{
    /**
     * For CurlRequest
     *
     * @return HTTPException
     *
     * @codeCoverageIgnore
     */
    public static function for_missing_curl()
    {
        return new static(lang('HTTP.missingCurl'));
    }
    /**
     * For CurlRequest
     *
     * @return HTTPException
     */
    public static function for_ssl_cert_not_found(string $cert)
    {
        return new static(lang('HTTP.sslCertNotFound', [$cert]));
    }
    /**
     * For CurlRequest
     *
     * @return HTTPException
     */
    public static function for_invalid_ssl_key(string $key)
    {
        return new static(lang('HTTP.invalidSSLKey', [$key]));
    }
    /**
     * For CurlRequest
     *
     * @return HTTPException
     *
     * @codeCoverageIgnore
     */
    public static function for_curl_error(string $error_num, string $error)
    {
        return new static(lang('HTTP.curlError', [$error_num, $error]));
    }
    /**
     * For IncomingRequest
     *
     * @return HTTPException
     */
    public static function for_invalid_negotiation_type(string $type)
    {
        return new static(lang('HTTP.invalidNegotiationType', [$type]));
    }
    /**
     * Thrown in IncomingRequest when the json_decode() produces
     *  an error code other than JSON_ERROR_NONE.
     *
     * @param string $error The error message
     *
     * @return static
     */
    public static function for_invalid_json(?string $error = null)
    {
        return new static(lang('HTTP.invalidJSON', [$error]));
    }
    /**
     * For Message
     *
     * @return HTTPException
     */
    public static function for_invalid_http_protocol(string $invalid_version)
    {
        return new static(lang('HTTP.invalidHTTPProtocol', [$invalid_version]));
    }
    /**
     * For Negotiate
     *
     * @return HTTPException
     */
    public static function for_empty_supported_negotiations()
    {
        return new static(lang('HTTP.emptySupportedNegotiations'));
    }
    /**
     * For RedirectResponse
     *
     * @return HTTPException
     */
    public static function for_invalid_redirect_route(string $route)
    {
        return new static(lang('HTTP.invalidRoute', [$route]));
    }
    /**
     * For Response
     *
     * @return HTTPException
     */
    public static function for_missing_response_status()
    {
        return new static(lang('HTTP.missingResponseStatus'));
    }
    /**
     * For Response
     *
     * @return HTTPException
     */
    public static function for_invalid_status_code(int $code)
    {
        return new static(lang('HTTP.invalidStatusCode', [$code]));
    }
    /**
     * For Response
     *
     * @return HTTPException
     */
    public static function for_unkown_status_code(int $code)
    {
        return new static(lang('HTTP.unknownStatusCode', [$code]));
    }
    /**
     * For URI
     *
     * @return HTTPException
     */
    public static function for_unable_to_parse_uri(string $uri)
    {
        return new static(lang('HTTP.cannotParseURI', [$uri]));
    }
    /**
     * For URI
     *
     * @return HTTPException
     */
    public static function for_uri_segment_out_of_range(int $segment)
    {
        return new static(lang('HTTP.segmentOutOfRange', [$segment]));
    }
    /**
     * For URI
     *
     * @return HTTPException
     */
    public static function for_invalid_port(int $port)
    {
        return new static(lang('HTTP.invalidPort', [$port]));
    }
    /**
     * For URI
     *
     * @return HTTPException
     */
    public static function for_malformed_query_string()
    {
        return new static(lang('HTTP.malformedQueryString'));
    }
    /**
     * For Uploaded file move
     *
     * @return HTTPException
     */
    public static function for_already_moved()
    {
        return new static(lang('HTTP.alreadyMoved'));
    }
    /**
     * For Uploaded file move
     *
     * @return HTTPException
     */
    public static function for_invalid_file(?string $path = null)
    {
        return new static(lang('HTTP.invalidFile'));
    }
    /**
     * For Uploaded file move
     *
     * @return HTTPException
     */
    public static function for_move_failed(string $source, string $target, string $error)
    {
        return new static(lang('HTTP.moveFailed', [$source, $target, $error]));
    }
    /**
     * For Invalid SameSite attribute setting
     *
     * @return HTTPException
     *
     * @deprecated Use `CookieException::forInvalidSameSite()` instead.
     *
     * @codeCoverageIgnore
     */
    public static function for_invalid_same_site_setting(string $samesite)
    {
        return new static(lang('Security.invalidSameSiteSetting', [$samesite]));
    }
    /**
     * Thrown when the JSON format is not supported.
     * This is specifically for cases where data validation is expected to work with key-value structures.
     *
     * @return HTTPException
     */
    public static function for_unsupported_json_format()
    {
        return new static(lang('HTTP.unsupportedJSONFormat'));
    }
}