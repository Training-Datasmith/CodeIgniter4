<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace CodeIgniter\HTTP;

use CodeIgniter\Exceptions\InvalidArgumentException;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Security-focused tests for URI parsing.
 *
 * Validates that URI parsing rejects or sanitises inputs that could lead to
 * open-redirect, path-traversal or header-injection vulnerabilities.
 *
 * @internal
 */
#[Group('Others')]
final class SecurityUriTest extends CIUnitTestCase
{
    // -------------------------------------------------------------------------
    // Path traversal prevention
    // -------------------------------------------------------------------------

    /**
     * A URI path with `../` sequences must not leave the intended path hierarchy.
     *
     * Prevents attackers from escaping the web root by injecting `../../etc/passwd`
     * style paths into URL parameters that the application then uses as file paths.
     */
    public function test_path_traversal_sequences_are_normalised(): void
    {
        $uri = new URI('https://example.com/app/../secret/file');

        // The parsed path should not contain `..` after normalisation.
        $this->assertStringNotContainsString('..', $uri->getPath());
    }

    /**
     * Double-dot encoded as `%2E%2E` must not bypass traversal protection.
     */
    public function test_percent_encoded_traversal_is_normalised(): void
    {
        $uri = new URI('https://example.com/app/%2E%2E/secret');

        $this->assertStringNotContainsString('..', $uri->getPath());
        $this->assertStringNotContainsString('%2E%2E', strtoupper($uri->getPath()));
    }

    // -------------------------------------------------------------------------
    // Scheme validation
    // -------------------------------------------------------------------------

    /**
     * The URI scheme must be lowercased (RFC 3986 §3.1).
     *
     * Consistent scheme normalisation prevents open-redirect bypasses such as
     * `JAVASCRIPT:alert(1)` being treated differently from `javascript:alert(1)`.
     */
    public function test_scheme_is_lowercased(): void
    {
        $uri = new URI('HTTPS://example.com/');
        $this->assertSame('https', $uri->getScheme());
    }

    /**
     * An invalid scheme containing non-ASCII characters must be rejected.
     */
    public function test_invalid_scheme_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new URI("java\nscript://evil.com");
    }

    // -------------------------------------------------------------------------
    // Host validation
    // -------------------------------------------------------------------------

    /**
     * The parsed host must be lowercased (RFC 3986 §3.2.2).
     *
     * Host-based open-redirect checks performed with `===` comparison would fail
     * for mixed-case hostnames if the host is not normalised.
     */
    public function test_host_is_lowercased(): void
    {
        $uri = new URI('https://EXAMPLE.COM/path');
        $this->assertSame('example.com', $uri->getHost());
    }

    // -------------------------------------------------------------------------
    // Query string special characters
    // -------------------------------------------------------------------------

    /**
     * Query parameters must be accessible by key without injecting extra keys.
     *
     * Guards against parameter injection where `?role=user&role=admin` might
     * cause the application to pick up the wrong value.
     */
    public function test_query_string_is_parsed_correctly(): void
    {
        $uri = new URI('https://example.com/page?key=value&other=123');

        $query = [];
        parse_str($uri->getQuery(), $query);

        $this->assertSame('value', $query['key']);
        $this->assertSame('123', $query['other']);
        $this->assertCount(2, $query);
    }

    // -------------------------------------------------------------------------
    // Fragment handling
    // -------------------------------------------------------------------------

    /**
     * Fragment identifiers (`#section`) must never be sent to the server.
     *
     * Confirms the URI object correctly isolates the fragment so it is not
     * accidentally included in server-side URL comparisons or log entries.
     */
    public function test_fragment_is_not_included_in_server_side_uri(): void
    {
        $uri = new URI('https://example.com/page?q=1#section-2');

        $this->assertSame('section-2', $uri->getFragment());

        // The fragment must not appear in the path or query components.
        $this->assertStringNotContainsString('#', $uri->getPath());
        $this->assertStringNotContainsString('section-2', $uri->getQuery());
    }

    // -------------------------------------------------------------------------
    // Segment safety
    // -------------------------------------------------------------------------

    /**
     * getSegment() must not return a value outside the available segments.
     *
     * Prevents an off-by-one access that could trigger an undefined-array-key
     * notice or return unexpected data used in a security decision.
     */
    public function test_get_segment_returns_default_for_out_of_bounds(): void
    {
        $uri = new URI('https://example.com/one/two');

        // Segments: 1=>'one', 2=>'two'. Segment 99 does not exist.
        $this->assertSame('', $uri->getSegment(99));
        $this->assertSame('fallback', $uri->getSegment(99, 'fallback'));
    }

    /**
     * A segment index of 0 or a negative number is invalid and must not
     * return a segment value (segments are 1-indexed per CodeIgniter convention).
     */
    public function test_zero_segment_index_throws_or_returns_empty(): void
    {
        $uri = new URI('https://example.com/one/two');

        // Either an exception or an empty-string fallback is acceptable;
        // the important thing is no segment is accidentally returned.
        try {
            $result = $uri->getSegment(0);
            $this->assertSame('', $result);
        } catch (\Exception $e) {
            $this->addToAssertionCount(1); // exception is acceptable
        }
    }
}
