<?php

declare(strict_types=1);

$response = $client->request('PUT', '/put', ['json' => ['foo' => 'bar']]);
