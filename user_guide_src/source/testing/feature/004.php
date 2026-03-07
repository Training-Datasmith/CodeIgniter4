<?php

declare(strict_types=1);

$routes = [
    ['GET', 'users', 'UserController::list'],
];

$result = $this->withRoutes($routes)->get('users');
