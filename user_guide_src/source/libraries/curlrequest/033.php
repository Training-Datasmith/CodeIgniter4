<?php

declare(strict_types=1);

// Force HTTP/1.0
$client->request('GET', '/', ['version' => 1.0]);
