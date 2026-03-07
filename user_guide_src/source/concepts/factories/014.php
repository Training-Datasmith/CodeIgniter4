<?php

declare(strict_types=1);

use CodeIgniter\Config\Factories;

Factories::define('models', 'Myth\Auth\Models\UserModel', 'App\Models\UserModel');
