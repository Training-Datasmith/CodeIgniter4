<?php

declare(strict_types=1);

namespace App\Controllers;

class HelloWorld extends BaseController
{
    public function getIndex()
    {
        return 'Hello World!';
    }
}
