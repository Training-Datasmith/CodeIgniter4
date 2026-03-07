<?php

declare(strict_types=1);

use App\Controllers\Home;

$routes->get('/', [Home::class, 'index']);
