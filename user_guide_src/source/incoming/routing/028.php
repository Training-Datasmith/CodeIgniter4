<?php

declare(strict_types=1);

$routes->environment('development', static function ($routes) {
    $routes->get('builder', 'Tools\Builder::index');
});
