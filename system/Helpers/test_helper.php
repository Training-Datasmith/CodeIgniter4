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
use Code_Igniter\Exceptions\Test_Exception;
use Code_Igniter\Model;
use Code_Igniter\Test\Fabricator;
use Config\Services;
// CodeIgniter Test Helpers
if (!function_exists('fake')) {
    /**
     * Creates a single item using Fabricator.
     *
     * @param Model|object|string $model     Instance or name of the model
     * @param array|null          $overrides Overriding data to pass to Fabricator::setOverrides()
     * @param bool                $persist
     *
     * @return array|object
     */
    function fake($model, ?array $overrides = null, $persist = true)
    {
        $fabricator = new Fabricator($model);
        if ($overrides !== null) {
            $fabricator->set_overrides($overrides);
        }
        if ($persist) {
            return $fabricator->create();
        }
        return $fabricator->make();
    }
}
if (!function_exists('mock')) {
    /**
     * Used within our test suite to mock certain system tools.
     *
     * @param string $className Fully qualified class name
     *
     * @return object
     */
    function mock(string $class_name)
    {
        $mock_class = $class_name::$mock_class;
        $mock_service = $class_name::$mock_service_name ?? '';
        if (empty($mock_class) || !class_exists($mock_class)) {
            throw Test_Exception::for_invalid_mock_class($mock_class);
        }
        $mock = new $mock_class();
        if (!empty($mock_service)) {
            Services::inject_mock($mock_service, $mock);
        }
        return $mock;
    }
}