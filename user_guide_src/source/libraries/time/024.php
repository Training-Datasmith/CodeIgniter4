<?php

declare(strict_types=1);

use CodeIgniter\I18n\Time;

echo Time::now()->getLocal();           // true
echo Time::now('Europe/London')->local; // false
