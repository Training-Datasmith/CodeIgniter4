<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Controller;

class UserController extends Controller
{
    public function index()
    {
        if ($this->request->isAJAX()) {
            // ...
        }
    }
}
