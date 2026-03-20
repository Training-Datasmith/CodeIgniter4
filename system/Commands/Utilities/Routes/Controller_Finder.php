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

use Code_Igniter\Autoloader\File_Locator_Interface;
/**
 * Finds all controllers in a namespace for auto route listing.
 *
 * @see \CodeIgniter\Commands\Utilities\Routes\ControllerFinderTest
 */
final readonly class Controller_Finder
{
    private File_Locator_Interface $locator;
    /**
     * @param string $namespace namespace to search
     */
    public function __construct(private string $namespace)
    {
        $this->locator = service('locator');
    }
    /**
     * @return list<class-string>
     */
    public function find(): array
    {
        $ns_array = explode('\\', trim($this->namespace, '\\'));
        $count = count($ns_array);
        $ns = '';
        $files = [];
        for ($i = 0; $i < $count; $i++) {
            $ns .= '\\' . array_shift($ns_array);
            $path = implode('\\', $ns_array);
            $files = $this->locator->list_namespace_files($ns, $path);
            if ($files !== []) {
                break;
            }
        }
        $classes = [];
        foreach ($files as $file) {
            if (\is_file($file)) {
                $classname_or_empty = $this->locator->get_classname($file);
                if ($classname_or_empty !== '') {
                    /** @var class-string $classname */
                    $classname = $classname_or_empty;
                    $classes[] = $classname;
                }
            }
        }
        return $classes;
    }
}