<?php

declare(strict_types=1);

// Limit to any sub-domain
$routes->get('from', 'to', ['subdomain' => '*']);
