<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Test\CIUnitTestCase;

final class UserModelTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp(); // Do not forget

        helper('text');
    }

    // ...
}
