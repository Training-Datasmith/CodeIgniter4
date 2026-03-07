<?php

declare(strict_types=1);

use CodeIgniter\I18n\Time;

$tz = Time::now()->getTimezone();
$tz = Time::now()->timezone;

echo $tz->getName();
echo $tz->getOffset();
