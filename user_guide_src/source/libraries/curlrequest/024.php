<?php

declare(strict_types=1);

$client->request('POST', '/post', [
    'form_params' => [
        'foo' => 'bar',
        'baz' => ['hi', 'there'],
    ],
]);
