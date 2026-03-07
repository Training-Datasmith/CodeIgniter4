<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;

class AppInfo extends BaseCommand
{
    protected $group       = 'Demo';
    protected $name        = 'app:info';
    protected $description = 'Displays basic application information.';

    public function run(array $params)
    {
        // ...
    }
}
