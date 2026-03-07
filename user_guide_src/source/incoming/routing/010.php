<?php

declare(strict_types=1);

$routes->get('product/(:any)', 'Catalog::productLookup/$1');
