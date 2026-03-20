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
namespace Code_Igniter\Filters;

use Code_Igniter\Cache\Response_Cache;
use Code_Igniter\HTTP\Cli_Request;
use Code_Igniter\HTTP\Download_Response;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Redirect_Response;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Config\Cache;
/**
 * Page Cache filter
 */
class Page_Cache implements Filter_Interface
{
    private readonly Response_Cache $page_cache;
    /**
     * @var list<int>
     */
    private readonly array $cache_status_codes;
    public function __construct(?Cache $config = null)
    {
        $config ??= config('Cache');
        $this->page_cache = service('responsecache');
        $this->cache_status_codes = $config->cache_status_codes ?? [];
    }
    /**
     * Checks page cache and return if found.
     *
     * @param array|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function before(Request_Interface $request, $arguments = null)
    {
        assert($request instanceof Cli_Request || $request instanceof Incoming_Request);
        $response = service('response');
        return $this->page_cache->get($request, $response);
    }
    /**
     * Cache the page.
     *
     * @param array|null $arguments
     */
    public function after(Request_Interface $request, Response_Interface $response, $arguments = null)
    {
        assert($request instanceof Cli_Request || $request instanceof Incoming_Request);
        if (!$response instanceof Download_Response && !$response instanceof Redirect_Response && ($this->cache_status_codes === [] || in_array($response->get_status_code(), $this->cache_status_codes, true))) {
            // Cache it without the performance metrics replaced
            // so that we can have live speed updates along the way.
            // Must be run after filters to preserve the Response headers.
            $this->page_cache->make($request, $response);
            return $response;
        }
        return null;
    }
}