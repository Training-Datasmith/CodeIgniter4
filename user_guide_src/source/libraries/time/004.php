<?php

declare(strict_types=1);

use CodeIgniter\I18n\Time;

$myTime = Time::parse('next Tuesday', 'America/Chicago', 'en_US');
