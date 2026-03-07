<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Database\Config;

/**
 * Database Configuration
 */
class Database extends Config
{
    // ...
    public $development = [/* ... */];
    public $test        = [/* ... */];
    public $production  = [/* ... */];

    public function __construct()
    {
        // ...

        $this->defaultGroup = ENVIRONMENT;
    }
}
