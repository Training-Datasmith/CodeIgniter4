<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class CustomClass extends BaseConfig
{
    public $siteName  = 'My Great Site';
    public $siteEmail = 'webmaster@example.com';
    // ...
}
