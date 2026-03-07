<?php

declare(strict_types=1);

use App\Models\UserModel;
use CodeIgniter\Test\Fabricator;

$formatters = [
    'first'  => 'firstName',
    'email'  => 'email',
    'phone'  => 'phoneNumber',
    'avatar' => 'imageUrl',
];

$fabricator = new Fabricator(UserModel::class, $formatters);
