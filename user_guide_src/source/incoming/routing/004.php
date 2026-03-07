<?php

declare(strict_types=1);

$routes->match(['GET', 'PUT'], 'products', 'Product::feature');
