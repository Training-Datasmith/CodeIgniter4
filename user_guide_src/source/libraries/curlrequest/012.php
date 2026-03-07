<?php

declare(strict_types=1);

if (str_contains($response->header('content-type'), 'application/json')) {
    $body = json_decode($body);
}
