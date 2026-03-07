<?php

declare(strict_types=1);

use CodeIgniter\Test\Fabricator;
use Tests\Support\Models\UserFabricator;

$fabricator = new Fabricator(UserFabricator::class);
$testUser   = $fabricator->make();
print_r($testUser);
