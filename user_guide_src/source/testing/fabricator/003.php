<?php

declare(strict_types=1);

use App\Models\UserModel;
use CodeIgniter\Test\Fabricator;

$model = new UserModel($testDbConnection);

$fabricator = new Fabricator($model);
