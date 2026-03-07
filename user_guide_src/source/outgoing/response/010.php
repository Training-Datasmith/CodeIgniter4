<?php

declare(strict_types=1);

$options = [
    'max-age'  => 300,
    's-maxage' => 900,
    'etag'     => 'abcde',
];
$this->response->setCache($options);
