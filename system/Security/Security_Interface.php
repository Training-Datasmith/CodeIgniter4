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
namespace Code_Igniter\Security;

use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\Security\Exceptions\Security_Exception;
/**
 * Expected behavior of a Security.
 */
interface Security_Interface
{
    /**
     * CSRF Verify
     *
     * @return $this|false
     *
     * @throws SecurityException
     */
    public function verify(Request_Interface $request);
    /**
     * Returns the CSRF Hash.
     */
    public function get_hash(): ?string;
    /**
     * Returns the CSRF Token Name.
     */
    public function get_token_name(): string;
    /**
     * Returns the CSRF Header Name.
     */
    public function get_header_name(): string;
    /**
     * Returns the CSRF Cookie Name.
     */
    public function get_cookie_name(): string;
    /**
     * Check if request should be redirect on failure.
     */
    public function should_redirect(): bool;
    /**
     * Sanitize Filename
     *
     * Tries to sanitize filenames in order to prevent directory traversal attempts
     * and other security threats, which is particularly useful for files that
     * were supplied via user input.
     *
     * If it is acceptable for the user input to include relative paths,
     * e.g. file/in/some/approved/folder.txt, you can set the second optional
     * parameter, $relativePath to TRUE.
     *
     * @deprecated 4.6.2 Use `sanitize_filename()` instead
     *
     * @param string $str          Input file name
     * @param bool   $relativePath Whether to preserve paths
     */
    public function sanitize_filename(string $str, bool $relative_path = false): string;
}