<?php

declare(strict_types=1);

// Get a header line
echo $response->getHeaderLine('Content-Type');

// Get all headers
foreach ($response->headers() as $name => $value) {
    echo $name . ': ' . $response->getHeaderLine($name) . "\n";
}
