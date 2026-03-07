<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Modules\Modules as BaseModules;

class Modules extends BaseModules
{
    // ...

    public $composerPackages = [
        'only' => [
            // List up all packages to auto-discover
            'codeigniter4/shield',
        ],
    ];

    // ...
}
