<?php

declare(strict_types=1);

$query->getUnbufferedRow();         // object
$query->getUnbufferedRow('object'); // object
$query->getUnbufferedRow('array');  // associative array
