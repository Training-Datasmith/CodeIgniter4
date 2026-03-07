<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Format extends BaseConfig
{
    public $supportedResponseFormats = [
        'application/json',
        'application/xml',
    ];

    // ...
}
