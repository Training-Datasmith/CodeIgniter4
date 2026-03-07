<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Controller;

class Tools extends Controller
{
    public function message($to = 'World')
    {
        return "Hello {$to}!" . PHP_EOL;
    }
}
