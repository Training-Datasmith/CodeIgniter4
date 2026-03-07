<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\AutoloadConfig;

class Autoload extends AutoloadConfig
{
    // ...

    public $files = [
        'path/to/my/functions.php',
        'path/to/my/constants.php',
        'path/to/my/bootstrap.php',
    ];

    // ...
}
