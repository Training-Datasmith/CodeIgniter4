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

/**
 * Collects data for auto route listing.
 *
 * @see \CodeIgniter\Commands\Utilities\Routes\AutoRouteCollectorTest
 */
final readonly class Auto_Route_Collector
{
    /**
     * @param string $namespace namespace to search
     */
    public function __construct(private string $namespace, private string $default_controller, private string $default_method)
    {
    }
    /**
     * @return list<list<string>>
     */
    public function get(): array
    {
        $finder = new Controller_Finder($this->namespace);
        $reader = new Controller_Method_Reader($this->namespace);
        $tbody = [];
        foreach ($finder->find() as $class) {
            $output = $reader->read($class, $this->default_controller, $this->default_method);
            foreach ($output as $item) {
                $tbody[] = ['auto', $item['route'], '', $item['handler']];
            }
        }
        return $tbody;
    }
}