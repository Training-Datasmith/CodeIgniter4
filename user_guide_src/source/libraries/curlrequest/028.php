<?php

declare(strict_types=1);

$client->request('POST', '/post', [
    'multipart' => [
        'foo'      => 'bar',
        'userfile' => new \CURLFile('/path/to/file.txt'),
    ],
]);
