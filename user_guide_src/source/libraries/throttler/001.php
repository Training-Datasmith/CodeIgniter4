<?php

declare(strict_types=1);

$throttler = service('throttler');
$throttler->check($name, 60, MINUTE);
