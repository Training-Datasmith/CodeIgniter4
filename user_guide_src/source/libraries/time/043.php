<?php

declare(strict_types=1);

use CodeIgniter\I18n\Time;

$time = Time::parse('yesterday');
echo $time->isPast(); // true
