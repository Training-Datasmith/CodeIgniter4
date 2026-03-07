<?php

declare(strict_types=1);

$type = $request->negotiate('encoding', ['gzip']);
// or
$type = $negotiate->encoding(['gzip']);
