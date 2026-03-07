<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class MyDatabaseTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    public function testBadRow()
    {
        // ...
    }
}
