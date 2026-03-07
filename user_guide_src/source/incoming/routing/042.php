<?php

declare(strict_types=1);

$routes->get('users/(:num)', 'users/show/$1', ['offset' => 1]);

// Creates:
$routes['users/(:num)'] = 'users/show/$2';
