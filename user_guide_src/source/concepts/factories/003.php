<?php

declare(strict_types=1);

use CodeIgniter\Config\Factories;

class SomeOtherClass
{
    public function someFunction()
    {
        $users = Factories::models('UserModel');

        // ...
    }
}
