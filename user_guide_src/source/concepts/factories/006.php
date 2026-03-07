<?php

declare(strict_types=1);

use CodeIgniter\Config\Factories;
use CodeIgniter\Filters\FilterInterface;

Factories::setOptions('filters', [
    'instanceOf' => FilterInterface::class,
    'prefersApp' => false,
]);
