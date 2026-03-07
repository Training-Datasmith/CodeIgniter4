<?php

declare(strict_types=1);

$options = [
    'baseURI' => 'http://example.com/api/v1/',
    'timeout' => 3,
];
$client = service('curlrequest', $options);
