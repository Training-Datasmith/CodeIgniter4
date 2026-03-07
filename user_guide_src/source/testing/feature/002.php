<?php

declare(strict_types=1);

// Get a simple page
$result = $this->call('GET', '/');

// Submit a form
$result = $this->call('post', 'contact', [
    'name'  => 'Fred Flintstone',
    'email' => 'flintyfred@example.com',
]);
