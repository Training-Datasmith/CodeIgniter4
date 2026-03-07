<?php

declare(strict_types=1);

namespace App\Controllers;

class MyController extends BaseController
{
    public function index()
    {
        return view('some_view');
    }
}
