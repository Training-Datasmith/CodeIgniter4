<?php

declare(strict_types=1);

// Limit to media.example.com
$routes->get('from', 'to', ['subdomain' => 'media']);
