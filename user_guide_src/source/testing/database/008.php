<?php

declare(strict_types=1);

$criteria = [
    'active' => 1,
];
$this->seeNumRecords(2, 'users', $criteria);
