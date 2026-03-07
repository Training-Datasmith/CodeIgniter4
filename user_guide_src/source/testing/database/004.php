<?php

declare(strict_types=1);

$criteria = [
    'email'  => 'joe@example.com',
    'active' => 1,
];
$this->dontSeeInDatabase('users', $criteria);
