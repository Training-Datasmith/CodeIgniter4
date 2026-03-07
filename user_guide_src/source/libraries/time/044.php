<?php

declare(strict_types=1);

use CodeIgniter\I18n\Time;

$time = Time::parse('tomorrow');
echo $time->isFuture(); // true
