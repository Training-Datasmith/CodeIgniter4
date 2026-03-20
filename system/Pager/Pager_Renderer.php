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
 * Class PagerRenderer
 *
 * This class is passed to the view that describes the pagination,
 * and is used to get the link information and provide utility
 * methods needed to work with pagination.
 *
 * @see \CodeIgniter\Pager\PagerRendererTest
 */
class Pager_Renderer
{
    /**
     * First page number in the set of links to be displayed.
     *
     * `first` and `last` will be updated by `setSurroundCount()`.
     * You must call `setSurroundCount()` after instantiation.
     *
     * @var int
     */
    protected $first = 1;
    /**
     * Last page number in the set of links to be displayed.
     *
     * @var int
     */
    protected $last;
    /**
     * Current page number.
     *
     * @var int
     */
    protected $current;
    /**
     * Total number of items.
     *
     * @var int
     */
    protected $total;
    /**
     * Total number of pages.
     *
     * @var int
     */
    protected $page_count;
    /**
     * URI base for pagination links
     *
     * @var URI
     */
    protected $uri;
    /**
     * Segment number used for pagination.
     *
     * @var int
     */
    protected $segment;
    /**
     * Name of $_GET parameter
     *
     * @var string
     */
    protected $page_selector;
    /**
     * Returns the number of results per page that should be shown.
     */
    protected ?int $per_page;
    /**
     * The number of items the page starts with.
     */
    protected ?int $per_page_start = null;
    /**
     * The number of items the page ends with.
     */
    protected ?int $per_page_end = null;
    /**
     * Constructor.
     */
    public function __construct(array $details)
    {
        $this->last = $details['pageCount'];
        $this->current = $details['currentPage'];
        $this->total = $details['total'];
        $this->uri = $details['uri'];
        $this->page_count = $details['pageCount'];
        $this->segment = $details['segment'] ?? 0;
        $this->page_selector = $details['pageSelector'] ?? 'page';
        $this->per_page = $details['perPage'] ?? null;
        $this->update_per_pages();
    }
    /**
     * Sets the total number of links that should appear on either
     * side of the current page. Adjusts the first and last counts
     * to reflect it.
     *
     * @return PagerRenderer
     */
    public function set_surround_count(?int $count = null)
    {
        $this->update_pages($count);
        return $this;
    }
    /**
     * Checks to see if there is a "previous" page before our "first" page.
     */
    public function has_previous(): bool
    {
        return $this->first > 1;
    }
    /**
     * Returns a URL to the "previous" page. The previous page is NOT the
     * page before the current page, but is the page just before the
     * "first" page.
     *
     * @return string|null
     */
    public function get_previous()
    {
        if (!$this->has_previous()) {
            return null;
        }
        $uri = clone $this->uri;
        if ($this->segment === 0) {
            $uri->add_query($this->page_selector, $this->first - 1);
        } else {
            $uri->set_segment($this->segment, $this->first - 1);
        }
        return URI::create_uri_string($uri->get_scheme(), $uri->get_authority(), $uri->get_path(), $uri->get_query(), $uri->get_fragment());
    }
    /**
     * Checks to see if there is a "next" page after our "last" page.
     */
    public function has_next(): bool
    {
        return $this->page_count > $this->last;
    }
    /**
     * Returns a URL to the "next" page. The next page is NOT, the
     * page after the current page, but is the page that follows the
     * "last" page.
     *
     * @return string|null
     */
    public function get_next()
    {
        if (!$this->has_next()) {
            return null;
        }
        $uri = clone $this->uri;
        if ($this->segment === 0) {
            $uri->add_query($this->page_selector, $this->last + 1);
        } else {
            $uri->set_segment($this->segment, $this->last + 1);
        }
        return URI::create_uri_string($uri->get_scheme(), $uri->get_authority(), $uri->get_path(), $uri->get_query(), $uri->get_fragment());
    }
    /**
     * Returns the URI of the first page.
     */
    public function get_first(): string
    {
        $uri = clone $this->uri;
        if ($this->segment === 0) {
            $uri->add_query($this->page_selector, 1);
        } else {
            $uri->set_segment($this->segment, 1);
        }
        return URI::create_uri_string($uri->get_scheme(), $uri->get_authority(), $uri->get_path(), $uri->get_query(), $uri->get_fragment());
    }
    /**
     * Returns the URI of the last page.
     */
    public function get_last(): string
    {
        $uri = clone $this->uri;
        if ($this->segment === 0) {
            $uri->add_query($this->page_selector, $this->page_count);
        } else {
            $uri->set_segment($this->segment, $this->page_count);
        }
        return URI::create_uri_string($uri->get_scheme(), $uri->get_authority(), $uri->get_path(), $uri->get_query(), $uri->get_fragment());
    }
    /**
     * Returns the URI of the current page.
     */
    public function get_current(): string
    {
        $uri = clone $this->uri;
        if ($this->segment === 0) {
            $uri->add_query($this->page_selector, $this->current);
        } else {
            $uri->set_segment($this->segment, $this->current);
        }
        return URI::create_uri_string($uri->get_scheme(), $uri->get_authority(), $uri->get_path(), $uri->get_query(), $uri->get_fragment());
    }
    /**
     * Returns an array of links that should be displayed. Each link
     * is represented by another array containing of the URI the link
     * should go to, the title (number) of the link, and a boolean
     * value representing whether this link is active or not.
     *
     * @return list<array{uri:string, title:int, active:bool}>
     */
    public function links(): array
    {
        $links = [];
        $uri = clone $this->uri;
        for ($i = $this->first; $i <= $this->last; $i++) {
            $uri = $this->segment === 0 ? $uri->add_query($this->page_selector, $i) : $uri->set_segment($this->segment, $i);
            $links[] = ['uri' => URI::create_uri_string($uri->get_scheme(), $uri->get_authority(), $uri->get_path(), $uri->get_query(), $uri->get_fragment()), 'title' => $i, 'active' => $i === $this->current];
        }
        return $links;
    }
    /**
     * Updates the first and last pages based on $surroundCount,
     * which is the number of links surrounding the active page
     * to show.
     *
     * @param int|null $count The new "surroundCount"
     *
     * @return void
     */
    protected function update_pages(?int $count = null)
    {
        if ($count === null) {
            return;
        }
        $this->first = $this->current - $count > 0 ? $this->current - $count : 1;
        $this->last = $this->current + $count <= $this->page_count ? $this->current + $count : (int) $this->page_count;
    }
    /**
     * Updates the start and end items per pages, which is
     * the number of items displayed on the active page.
     */
    protected function update_per_pages(): void
    {
        if ($this->total === null || $this->per_page === null) {
            return;
        }
        // When the page is the last, perform a different calculation.
        if ($this->last === $this->current) {
            $this->per_page_start = $this->per_page * ($this->current - 1) + 1;
            $this->per_page_end = $this->total;
            return;
        }
        $this->per_page_start = $this->current === 1 ? 1 : $this->per_page * $this->current - $this->per_page + 1;
        $this->per_page_end = $this->per_page * $this->current;
    }
    /**
     * Checks to see if there is a "previous" page before our "first" page.
     */
    public function has_previous_page(): bool
    {
        return $this->current > 1;
    }
    /**
     * Returns a URL to the "previous" page.
     *
     * You MUST call hasPreviousPage() first, or this value may be invalid.
     *
     * @return string|null
     */
    public function get_previous_page()
    {
        if (!$this->has_previous_page()) {
            return null;
        }
        $uri = clone $this->uri;
        if ($this->segment === 0) {
            $uri->add_query($this->page_selector, $this->current - 1);
        } else {
            $uri->set_segment($this->segment, $this->current - 1);
        }
        return URI::create_uri_string($uri->get_scheme(), $uri->get_authority(), $uri->get_path(), $uri->get_query(), $uri->get_fragment());
    }
    /**
     * Checks to see if there is a "next" page after our "last" page.
     */
    public function has_next_page(): bool
    {
        return $this->current < $this->last;
    }
    /**
     * Returns a URL to the "next" page.
     *
     * You MUST call hasNextPage() first, or this value may be invalid.
     *
     * @return string|null
     */
    public function get_next_page()
    {
        if (!$this->has_next_page()) {
            return null;
        }
        $uri = clone $this->uri;
        if ($this->segment === 0) {
            $uri->add_query($this->page_selector, $this->current + 1);
        } else {
            $uri->set_segment($this->segment, $this->current + 1);
        }
        return URI::create_uri_string($uri->get_scheme(), $uri->get_authority(), $uri->get_path(), $uri->get_query(), $uri->get_fragment());
    }
    /**
     * Returns the page number of the first page in the set of links to be displayed.
     */
    public function get_first_page_number(): int
    {
        return $this->first;
    }
    /**
     * Returns the page number of the current page.
     */
    public function get_current_page_number(): int
    {
        return $this->current;
    }
    /**
     * Returns the page number of the last page in the set of links to be displayed.
     */
    public function get_last_page_number(): int
    {
        return $this->last;
    }
    /**
     * Returns total number of pages.
     */
    public function get_page_count(): int
    {
        return $this->page_count;
    }
    /**
     * Returns the previous page number.
     */
    public function get_previous_page_number(): ?int
    {
        return $this->current === 1 ? null : $this->current - 1;
    }
    /**
     * Returns the next page number.
     */
    public function get_next_page_number(): ?int
    {
        return $this->current === $this->page_count ? null : $this->current + 1;
    }
    /**
     * Returns the total items of the page.
     */
    public function get_total(): ?int
    {
        return $this->total;
    }
    /**
     * Returns the number of items to be displayed on the page.
     */
    public function get_per_page(): ?int
    {
        return $this->per_page;
    }
    /**
     * Returns the number of items the page starts with.
     */
    public function get_per_page_start(): ?int
    {
        return $this->per_page_start;
    }
    /**
     * Returns the number of items the page ends with.
     */
    public function get_per_page_end(): ?int
    {
        return $this->per_page_end;
    }
}