<?php

declare(strict_types=1);

$client = service('curlrequest');

$response = $client->request('GET', 'https://api.github.com/user', [
    'auth' => ['user', 'pass'],
]);
