<?php

declare(strict_types=1);

$data = [
    'email' => 'joe@example.com',
    'name'  => 'Joe Cool',
];
$this->hasInDatabase('users', $data);
