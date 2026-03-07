<?php

declare(strict_types=1);

use CodeIgniter\I18n\Time;

$time = Time::create($year, $month, $day, $hour, $minutes, $seconds, $timezone, $locale);
