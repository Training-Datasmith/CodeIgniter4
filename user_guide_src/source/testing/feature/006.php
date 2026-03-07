<?php

declare(strict_types=1);

$headers = [
    'CONTENT_TYPE' => 'application/json',
];

$result = $this->withHeaders($headers)->post('users');
