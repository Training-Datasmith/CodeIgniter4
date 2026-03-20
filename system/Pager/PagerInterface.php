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
namespace Code_Igniter\Pager;

use Code_Igniter\HTTP\URI;
/**
 * Expected behavior for a Pager
 */
interface Pager_Interface
{
    /**
     * Handles creating and displaying the
     *
     * @param string $template The output template alias to render.
     */
    public function links(string $group = 'default', string $template = 'default'): string;
    /**
     * Creates simple Next/Previous links, instead of full pagination.
     */
    public function simple_links(string $group = 'default', string $template = 'default'): string;
    /**
     * Allows for a simple, manual, form of pagination where all of the data
     * is provided by the user. The URL is the current URI.
     *
     * @param string $template The output template alias to render.
     */
    public function make_links(int $page, int $per_page, int $total, string $template = 'default'): string;
    /**
     * Stores a set of pagination data for later display. Most commonly used
     * by the model to automate the process.
     *
     * @return $this
     */
    public function store(string $group, int $page, int $per_page, int $total);
    /**
     * Sets the path that an aliased group of links will use.
     *
     * @return $this
     */
    public function set_path(string $path, string $group = 'default');
    /**
     * Returns the total number of pages.
     */
    public function get_page_count(string $group = 'default'): int;
    /**
     * Returns the number of the current page of results.
     */
    public function get_current_page(string $group = 'default'): int;
    /**
     * Returns the URI for a specific page for the specified group.
     *
     * @return string|URI
     */
    public function get_page_uri(?int $page = null, string $group = 'default', bool $return_object = false);
    /**
     * Tells whether this group of results has any more pages of results.
     */
    public function has_more(string $group = 'default'): bool;
    /**
     * Returns the first page.
     *
     * @return int
     */
    public function get_first_page(string $group = 'default');
    /**
     * Returns the last page, if we have a total that we can calculate with.
     *
     * @return int|null
     */
    public function get_last_page(string $group = 'default');
    /**
     * Returns the full URI to the next page of results, or null.
     *
     * @return string|null
     */
    public function get_next_page_uri(string $group = 'default');
    /**
     * Returns the full URL to the previous page of results, or null.
     *
     * @return string|null
     */
    public function get_previous_page_uri(string $group = 'default');
    /**
     * Returns the number of results per page that should be shown.
     */
    public function get_per_page(string $group = 'default'): int;
    /**
     * Returns an array with details about the results, including
     * total, per_page, current_page, last_page, next_url, prev_url, from, to.
     * Does not include the actual data. This data is suitable for adding
     * a 'data' object to with the result set and converting to JSON.
     */
    public function get_details(string $group = 'default'): array;
}