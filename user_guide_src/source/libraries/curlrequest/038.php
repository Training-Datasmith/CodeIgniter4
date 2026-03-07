<?php

declare(strict_types=1);

$client->request('GET', 'http://example.com', ['fresh_connect' => true]);
$client->request('GET', 'http://example.com', ['fresh_connect' => false]);
