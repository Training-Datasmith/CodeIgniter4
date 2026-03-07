<?php

declare(strict_types=1);

use CodeIgniter\I18n\Time;

$dt   = new \DateTime('now');
$time = Time::createFromInstance($dt, 'en_US');
