<?php

declare(strict_types=1);

$format = $request->negotiate('media', $supported, true);
// or
$format = $negotiate->media($supported, true);
