<?php

declare(strict_types=1);

$routes->post('users/delete/(:segment)', 'AdminController::index', ['filter' => 'admin-auth:dual,noreturn']);
