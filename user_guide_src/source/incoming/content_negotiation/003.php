<?php

declare(strict_types=1);

$supported = [
    'application/json',
    'text/html',
    'application/xml',
];

$format = $request->negotiate('media', $supported);
// or
$format = $negotiate->media($supported);
