<?php

declare(strict_types=1);

use CodeIgniter\Config\Factories;

$users = Factories::models('Blog\Models\UserModel');
// Or
$users = Factories::models(\Blog\Models\UserModel::class);
