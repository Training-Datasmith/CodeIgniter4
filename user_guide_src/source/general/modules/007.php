<?php

declare(strict_types=1);

$routes->group('blog', ['namespace' => 'Acme\Blog\Controllers'], static function ($routes) {
    $routes->get('/', 'Blog::index');
});
