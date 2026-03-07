<?php

declare(strict_types=1);

// Get the router instance.
/** @var \CodeIgniter\Router\Router $router */
$router  = service('router');
$filters = $router->getFilters();

echo 'Active Filters for the Route: ' . implode(', ', $filters);
