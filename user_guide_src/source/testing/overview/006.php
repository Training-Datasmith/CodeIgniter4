<?php

declare(strict_types=1);

namespace App\Traits;

trait AuthTrait
{
    protected function setUpAuthTrait()
    {
        $user = $this->createFakeUser();
        $this->logInUser($user);
    }

    // ...
}
