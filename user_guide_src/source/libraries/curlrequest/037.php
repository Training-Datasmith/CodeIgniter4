<?php

declare(strict_types=1);

// Modify default DNS Cache Timeout
$client->request('GET', '/', ['dns_cache_timeout' => 360]); // seconds
