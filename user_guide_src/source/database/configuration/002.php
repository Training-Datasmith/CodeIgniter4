<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Database\Config;

class Database extends Config
{
    // ...

    // OCI8
    public array $default = [
        'DSN' => '//localhost/XE',
        // ...
    ];

    // ...
}
