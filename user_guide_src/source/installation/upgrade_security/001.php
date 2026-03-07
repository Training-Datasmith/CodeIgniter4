<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Filters extends BaseConfig
{
    // ...

    public $globals = [
        'before' => [
            // 'honeypot',
            'csrf',
        ],
    ];

    // ...
}
