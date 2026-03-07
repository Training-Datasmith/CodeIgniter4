<?php

declare(strict_types=1);

$bytes     = $file->getSizeByUnit(); // 256901
$kilobytes = $file->getSizeByUnit('kb'); // 250.880
$megabytes = $file->getSizeByUnit('mb'); // 0.245
