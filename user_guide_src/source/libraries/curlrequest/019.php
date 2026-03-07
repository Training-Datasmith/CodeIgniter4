<?php

declare(strict_types=1);

$client->request('GET', '/', ['cert' => ['/path/server.pem', 'password']]);
