<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Exceptions extends BaseConfig
{
    // ...
    public array $ignoreCodes = [404];
    // ...
}
