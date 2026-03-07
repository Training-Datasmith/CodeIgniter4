<?php

declare(strict_types=1);

$routes->get('admin', ' AdminController::index', ['filter' => ['admin-auth', \App\Filters\SomeFilter::class]]);
