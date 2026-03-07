<?php

declare(strict_types=1);

// Delay for 2 seconds
$client->request('GET', 'http://example.com', ['delay' => 2000]);
