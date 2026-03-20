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
namespace Code_Igniter\Commands\Utilities\Routes;

use Code_Igniter\Router\Route_Collection;
use Config\App;
/**
 * Generate a sample URI path from route key regex.
 *
 * @see \CodeIgniter\Commands\Utilities\Routes\SampleURIGeneratorTest
 */
final class Sample_Uri_Generator
{
    private readonly Route_Collection $routes;
    /**
     * Sample URI path for placeholder.
     *
     * @var array<string, string>
     */
    private array $samples = ['any' => '123/abc', 'segment' => 'abc_123', 'alphanum' => 'abc123', 'num' => '123', 'alpha' => 'abc', 'hash' => 'abc_123'];
    public function __construct(?Route_Collection $routes = null)
    {
        $this->routes = $routes ?? service('routes');
    }
    /**
     * @param string $routeKey route key regex
     *
     * @return string sample URI path
     */
    public function get(string $route_key): string
    {
        $sample_uri = $route_key;
        if (str_contains($route_key, '{locale}')) {
            $sample_uri = str_replace('{locale}', config(App::class)->default_locale, $route_key);
        }
        foreach ($this->routes->get_placeholders() as $placeholder => $regex) {
            $sample = $this->samples[$placeholder] ?? '::unknown::';
            $sample_uri = str_replace('(' . $regex . ')', $sample, $sample_uri);
        }
        // auto route
        return str_replace('[/...]', '/1/2/3/4/5', $sample_uri);
    }
}