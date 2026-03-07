<?php

declare(strict_types=1);

$routes->get('product/(:num)', 'Catalog::productLookup');
