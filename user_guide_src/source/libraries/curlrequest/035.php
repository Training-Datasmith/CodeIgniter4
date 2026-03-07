<?php

declare(strict_types=1);

$client->request(
    'GET',
    'http://example.com',
    ['proxy' => 'http://localhost:3128'],
);
