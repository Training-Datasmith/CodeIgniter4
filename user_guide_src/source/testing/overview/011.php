<?php

declare(strict_types=1);

use CodeIgniter\Debug\Timer;

$timer = new Timer();
$timer->start('longjohn', strtotime('-11 minutes'));
$this->assertCloseEnough(11 * 60, $timer->getElapsedTime('longjohn'));
