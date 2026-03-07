<?php

declare(strict_types=1);

$client->request('GET', '/', [
    'headers' => [
        'User-Agent' => 'testing/1.0',
        'Accept'     => 'application/json',
        'X-Foo'      => ['Bar', 'Baz'],
    ],
]);
