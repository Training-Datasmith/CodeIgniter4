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

use Code_Igniter\HTTP\Exceptions\Http_Exception;
use Code_Igniter\HTTP\URI;
use Code_Igniter\Pager\Exceptions\Pager_Exception;
use Code_Igniter\View\Renderer_Interface;
use Config\Pager as PagerConfig;
/**
 * Class Pager
 *
 * The Pager class provides semi-automatic and manual methods for creating
 * pagination links and reading the current url's query variable, "page"
 * to determine the current page. This class can support multiple
 * paginations on a single page.
 *
 * @see \CodeIgniter\Pager\PagerTest
 */
class Pager implements Pager_Interface
{
    /**
     * The group data.
     *
     * @var array
     */
    protected $groups = [];
    /**
     * URI segment for groups if provided.
     *
     * @var array
     */
    protected $segment = [];
    /**
     * Our configuration instance.
     *
     * @var PagerConfig
     */
    protected $config;
    /**
     * The view engine to render the links with.
     *
     * @var RendererInterface
     */
    protected $view;
    /**
     * List of only permitted queries
     *
     * @var list<string>|null
     */
    protected $only;
    /**
     * Constructor.
     */
    public function __construct(Pager_Config $config, Renderer_Interface $view)
    {
        $this->config = $config;
        $this->view = $view;
    }
    /**
     * Handles creating and displaying the
     *
     * @param string $template The output template alias to render.
     */
    public function links(string $group = 'default', string $template = 'default_full'): string
    {
        $this->ensure_group($group);
        return $this->display_links($group, $template);
    }
    /**
     * Creates simple Next/Previous links, instead of full pagination.
     */
    public function simple_links(string $group = 'default', string $template = 'default_simple'): string
    {
        $this->ensure_group($group);
        return $this->display_links($group, $template);
    }
    /**
     * Allows for a simple, manual, form of pagination where all of the data
     * is provided by the user. The URL is the current URI.
     *
     * @param string      $template The output template alias to render.
     * @param int         $segment  (whether page number is provided by URI segment)
     * @param string|null $group    optional group (i.e. if we'd like to define custom path)
     */
    public function make_links(int $page, ?int $per_page, int $total, string $template = 'default_full', int $segment = 0, ?string $group = 'default'): string
    {
        $group = $group === '' ? 'default' : $group;
        $this->store($group, $page, $per_page ?? $this->config->per_page, $total, $segment);
        return $this->display_links($group, $template);
    }
    /**
     * Does the actual work of displaying the view file. Used internally
     * by links(), simpleLinks(), and makeLinks().
     */
    protected function display_links(string $group, string $template): string
    {
        if (!array_key_exists($template, $this->config->templates)) {
            throw Pager_Exception::for_invalid_template($template);
        }
        $pager = new Pager_Renderer($this->get_details($group));
        return $this->view->set_var('pager', $pager)->render($this->config->templates[$template]);
    }
    /**
     * Stores a set of pagination data for later display. Most commonly used
     * by the model to automate the process.
     *
     * @return $this
     */
    public function store(string $group, int $page, ?int $per_page, int $total, int $segment = 0)
    {
        if ($segment !== 0) {
            $this->set_segment($segment, $group);
        }
        $this->ensure_group($group, $per_page);
        if ($segment > 0 && $this->groups[$group]['currentPage'] > 0) {
            $page = $this->groups[$group]['currentPage'];
        }
        $per_page ??= $this->config->per_page;
        $page_count = (int) ceil($total / $per_page);
        $this->groups[$group]['currentPage'] = $page > $page_count ? $page_count : $page;
        $this->groups[$group]['perPage'] = $per_page;
        $this->groups[$group]['total'] = $total;
        $this->groups[$group]['pageCount'] = $page_count;
        return $this;
    }
    /**
     * Sets segment for a group.
     *
     * @return $this
     */
    public function set_segment(int $number, string $group = 'default')
    {
        $this->segment[$group] = $number;
        // Recalculate current page
        $this->ensure_group($group);
        $this->calculate_current_page($group);
        return $this;
    }
    /**
     * Sets the path that an aliased group of links will use.
     *
     * @return $this
     */
    public function set_path(string $path, string $group = 'default')
    {
        $this->ensure_group($group);
        $this->groups[$group]['uri']->set_path($path);
        return $this;
    }
    /**
     * Returns the total number of items in data store.
     */
    public function get_total(string $group = 'default'): int
    {
        $this->ensure_group($group);
        return $this->groups[$group]['total'];
    }
    /**
     * Returns the total number of pages.
     */
    public function get_page_count(string $group = 'default'): int
    {
        $this->ensure_group($group);
        return $this->groups[$group]['pageCount'];
    }
    /**
     * Returns the number of the current page of results.
     */
    public function get_current_page(string $group = 'default'): int
    {
        $this->ensure_group($group);
        return $this->groups[$group]['currentPage'] ?: 1;
    }
    /**
     * Tells whether this group of results has any more pages of results.
     */
    public function has_more(string $group = 'default'): bool
    {
        $this->ensure_group($group);
        return $this->groups[$group]['currentPage'] * $this->groups[$group]['perPage'] < $this->groups[$group]['total'];
    }
    /**
     * Returns the last page, if we have a total that we can calculate with.
     *
     * @return int|null
     */
    public function get_last_page(string $group = 'default')
    {
        $this->ensure_group($group);
        if (!is_numeric($this->groups[$group]['total']) || !is_numeric($this->groups[$group]['perPage'])) {
            return null;
        }
        return (int) ceil($this->groups[$group]['total'] / $this->groups[$group]['perPage']);
    }
    /**
     * Determines the first page # that should be shown.
     */
    public function get_first_page(string $group = 'default'): int
    {
        $this->ensure_group($group);
        // @todo determine based on a 'surroundCount' value
        return 1;
    }
    /**
     * Returns the URI for a specific page for the specified group.
     *
     * @return string|URI
     */
    public function get_page_uri(?int $page = null, string $group = 'default', bool $return_object = false)
    {
        $this->ensure_group($group);
        /**
         * @var URI $uri
         */
        $uri = $this->groups[$group]['uri'];
        $segment = $this->segment[$group] ?? 0;
        if ($segment) {
            $uri->set_segment($segment, $page);
        } else {
            $uri->add_query($this->groups[$group]['pageSelector'], $page);
        }
        if ($this->only !== null) {
            $query = array_intersect_key(service('superglobals')->get_get_array(), array_flip($this->only));
            if (!$segment) {
                $query[$this->groups[$group]['pageSelector']] = $page;
            }
            $uri->set_query_array($query);
        }
        return $return_object ? $uri : URI::create_uri_string($uri->get_scheme(), $uri->get_authority(), $uri->get_path(), $uri->get_query(), $uri->get_fragment());
    }
    /**
     * Returns the full URI to the next page of results, or null.
     *
     * @return string|null
     */
    public function get_next_page_uri(string $group = 'default', bool $return_object = false)
    {
        $this->ensure_group($group);
        $last = $this->get_last_page($group);
        $curr = $this->get_current_page($group);
        $page = null;
        if (!empty($last) && $curr !== 0 && $last === $curr) {
            return null;
        }
        if ($last > $curr) {
            $page = $curr + 1;
        }
        return $this->get_page_uri($page, $group, $return_object);
    }
    /**
     * Returns the full URL to the previous page of results, or null.
     *
     * @return string|null
     */
    public function get_previous_page_uri(string $group = 'default', bool $return_object = false)
    {
        $this->ensure_group($group);
        $first = $this->get_first_page($group);
        $curr = $this->get_current_page($group);
        $page = null;
        if ($first !== 0 && $curr !== 0 && $first === $curr) {
            return null;
        }
        if ($first < $curr) {
            $page = $curr - 1;
        }
        return $this->get_page_uri($page, $group, $return_object);
    }
    /**
     * Returns the number of results per page that should be shown.
     */
    public function get_per_page(string $group = 'default'): int
    {
        $this->ensure_group($group);
        return (int) $this->groups[$group]['perPage'];
    }
    /**
     * Returns an array with details about the results, including
     * total, per_page, current_page, last_page, next_url, prev_url, from, to.
     * Does not include the actual data. This data is suitable for adding
     * a 'data' object to with the result set and converting to JSON.
     */
    public function get_details(string $group = 'default'): array
    {
        if (!array_key_exists($group, $this->groups)) {
            throw Pager_Exception::for_invalid_pagination_group($group);
        }
        $new_group = $this->groups[$group];
        $new_group['next'] = $this->get_next_page_uri($group);
        $new_group['previous'] = $this->get_previous_page_uri($group);
        $new_group['segment'] = $this->segment[$group] ?? 0;
        return $new_group;
    }
    /**
     * Sets only allowed queries on pagination links.
     */
    public function only(array $queries): self
    {
        $this->only = $queries;
        return $this;
    }
    /**
     * Ensures that an array exists for the group specified.
     *
     * @return void
     */
    protected function ensure_group(string $group, ?int $per_page = null)
    {
        if (array_key_exists($group, $this->groups)) {
            return;
        }
        $this->groups[$group] = ['currentUri' => clone current_url(true), 'uri' => clone current_url(true), 'hasMore' => false, 'total' => null, 'perPage' => $per_page ?? $this->config->per_page, 'pageCount' => 1, 'pageSelector' => $group === 'default' ? 'page' : 'page_' . $group];
        $this->calculate_current_page($group);
        $get = service('superglobals')->get_get_array();
        if ($get !== []) {
            $this->groups[$group]['uri'] = $this->groups[$group]['uri']->set_query_array($get);
        }
    }
    /**
     * Calculating the current page
     *
     * @return void
     */
    protected function calculate_current_page(string $group)
    {
        if (array_key_exists($group, $this->segment)) {
            try {
                $this->groups[$group]['currentPage'] = (int) $this->groups[$group]['currentUri']->set_silent(false)->get_segment($this->segment[$group]);
            } catch (Http_Exception) {
                $this->groups[$group]['currentPage'] = 1;
            }
        } else {
            $page_selector = $this->groups[$group]['pageSelector'];
            $page = (int) service('superglobals')->get($page_selector, '1');
            $this->groups[$group]['currentPage'] = $page < 1 ? 1 : $page;
        }
    }
}