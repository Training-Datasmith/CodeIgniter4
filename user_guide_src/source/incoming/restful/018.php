<?php

declare(strict_types=1);

use App\Controllers\Gallery;

$routes->resource('photos', ['namespace' => '', 'controller' => Gallery::class]);
// Would create routes like:
$routes->get('photos', '\App\Controllers\Gallery::index');
