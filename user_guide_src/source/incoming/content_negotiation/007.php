<?php

declare(strict_types=1);

$charset = $request->negotiate('charset', ['utf-8']);
// or
$charset = $negotiate->charset(['utf-8']);
