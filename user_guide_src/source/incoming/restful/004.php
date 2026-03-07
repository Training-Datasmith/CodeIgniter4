<?php

declare(strict_types=1);

$routes->resource('photos', ['placeholder' => '(:num)']);

// Generates routes like:
$routes->get('photos/(:num)', 'Photos::show/$1');
