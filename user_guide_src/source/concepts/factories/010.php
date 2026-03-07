<?php

declare(strict_types=1);

use CodeIgniter\Config\Factories;

$users = Factories::models('Blog\Models\UserModel', ['preferApp' => false]);
