<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Logger extends BaseConfig
{
    // Log only debug and info type messages
    public $threshold = [5, 8];

    // ...
}
