<?php

declare(strict_types=1);

// Routes to \Admin\Users::index()
$routes->get('admin/users', 'Users::index', ['namespace' => 'Admin']);
