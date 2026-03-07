<?php

declare(strict_types=1);

$routes->group('api', ['filter' => 'api-auth'], static function ($routes) {
    $routes->resource('users');
});
