<?php

declare(strict_types=1);

$multipleRoutes = [
    'product/(:num)'      => 'Catalog::productLookupById',
    'product/(:alphanum)' => 'Catalog::productLookupByName',
];

$routes->map($multipleRoutes);
