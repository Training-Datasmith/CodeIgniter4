<?php

declare(strict_types=1);

use CodeIgniter\I18n\Time;

echo Time::now('America/Chicago')->getUtc(); // false
echo Time::now('UTC')->utc;                  // true
