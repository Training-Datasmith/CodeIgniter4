<?php

declare(strict_types=1);

$routes->group('api', ['namespace' => 'App\API\v1'], static function ($routes) {
    $routes->resource('users');
});
