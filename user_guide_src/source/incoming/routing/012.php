<?php

declare(strict_types=1);

$routes->get('product/(:segment)', 'Catalog::productLookup/$1');
