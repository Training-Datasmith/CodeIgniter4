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
namespace Code_Igniter\HTTP;

use Code_Igniter\HTTP\Exceptions\Http_Exception;
use Config\Feature;
/**
 * Class Negotiate
 *
 * Provides methods to negotiate with the HTTP headers to determine the best
 * type match between what the application supports and what the requesting
 * server wants.
 *
 * @see http://tools.ietf.org/html/rfc7231#section-5.3
 * @see \CodeIgniter\HTTP\NegotiateTest
 */
class Negotiate
{
    /**
     * Request
     *
     * @var IncomingRequest
     */
    protected $request;
    /**
     * Constructor
     */
    public function __construct(?Request_Interface $request = null)
    {
        if ($request instanceof Request_Interface) {
            assert($request instanceof Incoming_Request);
            $this->request = $request;
        }
    }
    /**
     * Stores the request instance to grab the headers from.
     *
     * @return $this
     */
    public function set_request(Request_Interface $request)
    {
        assert($request instanceof Incoming_Request);
        $this->request = $request;
        return $this;
    }
    /**
     * Determines the best content-type to use based on the $supported
     * types the application says it supports, and the types requested
     * by the client.
     *
     * If no match is found, the first, highest-ranking client requested
     * type is returned.
     *
     * @param bool $strictMatch If TRUE, will return an empty string when no match found.
     *                          If FALSE, will return the first supported element.
     */
    public function media(array $supported, bool $strict_match = false): string
    {
        return $this->get_best_match($supported, $this->request->get_header_line('accept'), true, $strict_match);
    }
    /**
     * Determines the best charset to use based on the $supported
     * types the application says it supports, and the types requested
     * by the client.
     *
     * If no match is found, the first, highest-ranking client requested
     * type is returned.
     */
    public function charset(array $supported): string
    {
        $match = $this->get_best_match($supported, $this->request->get_header_line('accept-charset'), false, true);
        // If no charset is shown as a match, ignore the directive
        // as allowed by the RFC, and tell it a default value.
        if ($match === '') {
            return 'utf-8';
        }
        return $match;
    }
    /**
     * Determines the best encoding type to use based on the $supported
     * types the application says it supports, and the types requested
     * by the client.
     *
     * If no match is found, the first, highest-ranking client requested
     * type is returned.
     */
    public function encoding(array $supported = []): string
    {
        $supported[] = 'identity';
        return $this->get_best_match($supported, $this->request->get_header_line('accept-encoding'));
    }
    /**
     * Determines the best language to use based on the $supported
     * types the application says it supports, and the types requested
     * by the client.
     *
     * If strict locale negotiation is disabled and no match is found, the first, highest-ranking client requested
     * type is returned.
     */
    public function language(array $supported): string
    {
        if (config(Feature::class)->strict_locale_negotiation) {
            return $this->get_best_locale_match($supported, $this->request->get_header_line('accept-language'));
        }
        return $this->get_best_match($supported, $this->request->get_header_line('accept-language'), false, false, true);
    }
    // --------------------------------------------------------------------
    // Utility Methods
    // --------------------------------------------------------------------
    /**
     * Does the grunt work of comparing any of the app-supported values
     * against a given Accept* header string.
     *
     * Portions of this code base on Aura.Accept library.
     *
     * @param array  $supported    App-supported values
     * @param string $header       header string
     * @param bool   $enforceTypes If TRUE, will compare media types and sub-types.
     * @param bool   $strictMatch  If TRUE, will return empty string on no match.
     *                             If FALSE, will return the first supported element.
     * @param bool   $matchLocales If TRUE, will match locale sub-types to a broad type (fr-FR = fr)
     *
     * @return string Best match
     */
    protected function get_best_match(array $supported, ?string $header = null, bool $enforce_types = false, bool $strict_match = false, bool $match_locales = false): string
    {
        if ($supported === []) {
            throw Http_Exception::for_empty_supported_negotiations();
        }
        if ($header === null || $header === '') {
            return $strict_match ? '' : $supported[0];
        }
        $acceptable = $this->parse_header($header);
        foreach ($acceptable as $accept) {
            // if acceptable quality is zero, skip it.
            if ($accept['q'] === 0.0) {
                continue;
            }
            // if acceptable value is "anything", return the first available
            if ($accept['value'] === '*' || $accept['value'] === '*/*') {
                return $supported[0];
            }
            // If an acceptable value is supported, return it
            foreach ($supported as $available) {
                if ($this->match($accept, $available, $enforce_types, $match_locales)) {
                    return $available;
                }
            }
        }
        // No matches? Return the first supported element.
        return $strict_match ? '' : $supported[0];
    }
    /**
     * Try to find the best matching locale. It supports strict locale comparison.
     *
     * If Config\App::$supportedLocales have "en-US" and "en-GB" locales, they can be recognized
     * as two different locales. This method checks first for the strict match, then fallback
     * to the most general locale (in this case "en") ISO 639-1 and finally to the locale variant
     * "en-*" (ISO 639-1 plus "wildcard" for ISO 3166-1 alpha-2).
     *
     * If nothing from above is matched, then it returns the first option from the $supportedLocales array.
     *
     * @param list<string> $supportedLocales App-supported values
     * @param ?string      $header           Compatible 'Accept-Language' header string
     */
    protected function get_best_locale_match(array $supported_locales, ?string $header): string
    {
        if ($supported_locales === []) {
            throw Http_Exception::for_empty_supported_negotiations();
        }
        if ($header === null || $header === '') {
            return $supported_locales[0];
        }
        $acceptable = $this->parse_header($header);
        $fallback_locales = [];
        foreach ($acceptable as $accept) {
            // if acceptable quality is zero, skip it.
            if ($accept['q'] === 0.0) {
                continue;
            }
            // if acceptable value is "anything", return the first available
            if ($accept['value'] === '*') {
                return $supported_locales[0];
            }
            // look for exact match
            if (in_array($accept['value'], $supported_locales, true)) {
                return $accept['value'];
            }
            // set a fallback locale
            $fallback_locales[] = strtok($accept['value'], '-');
        }
        foreach ($fallback_locales as $fallback_locale) {
            // look for exact match
            if (in_array($fallback_locale, $supported_locales, true)) {
                return $fallback_locale;
            }
            // look for regional locale match
            foreach ($supported_locales as $locale) {
                if (str_starts_with($locale, $fallback_locale . '-')) {
                    return $locale;
                }
            }
        }
        return $supported_locales[0];
    }
    /**
     * Parses an Accept* header into it's multiple values.
     *
     * This is based on code from Aura.Accept library.
     */
    public function parse_header(string $header): array
    {
        $results = [];
        $acceptable = explode(',', $header);
        foreach ($acceptable as $value) {
            $pairs = explode(';', $value);
            $value = $pairs[0];
            unset($pairs[0]);
            $parameters = [];
            foreach ($pairs as $pair) {
                if (preg_match('/^(?P<name>.+?)=(?P<quoted>"|\')?(?P<value>.*?)(?:\k<quoted>)?$/', $pair, $param)) {
                    $parameters[trim($param['name'])] = trim($param['value']);
                }
            }
            $quality = 1.0;
            if (array_key_exists('q', $parameters)) {
                $quality = $parameters['q'];
                unset($parameters['q']);
            }
            $results[] = ['value' => trim($value), 'q' => (float) $quality, 'params' => $parameters];
        }
        // Sort to get the highest results first
        usort($results, static function ($a, $b): int {
            if ($a['q'] === $b['q']) {
                $a_ast = substr_count($a['value'], '*');
                $b_ast = substr_count($b['value'], '*');
                // '*/*' has lower precedence than 'text/*',
                // and 'text/*' has lower priority than 'text/plain'
                //
                // This seems backwards, but needs to be that way
                // due to the way PHP7 handles ordering or array
                // elements created by reference.
                if ($a_ast > $b_ast) {
                    return 1;
                }
                // If the counts are the same, but one element
                // has more params than another, it has higher precedence.
                //
                // This seems backwards, but needs to be that way
                // due to the way PHP7 handles ordering or array
                // elements created by reference.
                if ($a_ast === $b_ast) {
                    return count($b['params']) - count($a['params']);
                }
                return 0;
            }
            // Still here? Higher q values have precedence.
            return $a['q'] > $b['q'] ? -1 : 1;
        });
        return $results;
    }
    /**
     * Match-maker
     *
     * @param bool $matchLocales
     */
    protected function match(array $acceptable, string $supported, bool $enforce_types = false, $match_locales = false): bool
    {
        $supported = $this->parse_header($supported);
        if (count($supported) === 1) {
            $supported = $supported[0];
        }
        // Is it an exact match?
        if ($acceptable['value'] === $supported['value']) {
            return $this->match_parameters($acceptable, $supported);
        }
        // Do we need to compare types/sub-types? Only used
        // by negotiateMedia().
        if ($enforce_types) {
            return $this->match_types($acceptable, $supported);
        }
        // Do we need to match locales against broader locales?
        if ($match_locales) {
            return $this->match_locales($acceptable, $supported);
        }
        return false;
    }
    /**
     * Checks two Accept values with matching 'values' to see if their
     * 'params' are the same.
     */
    protected function match_parameters(array $acceptable, array $supported): bool
    {
        if (count($acceptable['params']) !== count($supported['params'])) {
            return false;
        }
        foreach ($supported['params'] as $label => $value) {
            if (!isset($acceptable['params'][$label]) || $acceptable['params'][$label] !== $value) {
                return false;
            }
        }
        return true;
    }
    /**
     * Compares the types/subtypes of an acceptable Media type and
     * the supported string.
     */
    public function match_types(array $acceptable, array $supported): bool
    {
        // PHPDocumentor v2 cannot parse yet the shorter list syntax,
        // causing no API generation for the file.
        [$a_type, $a_sub_type] = explode('/', $acceptable['value']);
        [$s_type, $s_sub_type] = explode('/', $supported['value']);
        // If the types don't match, we're done.
        if ($a_type !== $s_type) {
            return false;
        }
        // If there's an asterisk, we're cool
        if ($a_sub_type === '*') {
            return true;
        }
        // Otherwise, subtypes must match also.
        return $a_sub_type === $s_sub_type;
    }
    /**
     * Will match locales against their broader pairs, so that fr-FR would
     * match a supported localed of fr
     */
    public function match_locales(array $acceptable, array $supported): bool
    {
        $a_broad = mb_strpos($acceptable['value'], '-') > 0 ? mb_substr($acceptable['value'], 0, mb_strpos($acceptable['value'], '-')) : $acceptable['value'];
        $s_broad = mb_strpos($supported['value'], '-') > 0 ? mb_substr($supported['value'], 0, mb_strpos($supported['value'], '-')) : $supported['value'];
        return strtolower($a_broad) === strtolower($s_broad);
    }
}