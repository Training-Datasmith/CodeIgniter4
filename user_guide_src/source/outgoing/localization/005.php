<?php

declare(strict_types=1);

namespace App\Controllers;

class UserController extends BaseController
{
    public function index()
    {
        $locale = $this->request->getLocale();
    }
}
