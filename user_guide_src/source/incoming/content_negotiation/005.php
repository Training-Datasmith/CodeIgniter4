<?php

declare(strict_types=1);

$supported = [
    'en',
    'de',
];

$lang = $request->negotiate('language', $supported);
// or
$lang = $negotiate->language($supported);
