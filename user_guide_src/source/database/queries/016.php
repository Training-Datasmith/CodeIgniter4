<?php

declare(strict_types=1);

$pQuery = $db->prepare(static fn ($db) => $db->table('user')->insert([
    'name'    => 'x',
    'email'   => 'y',
    'country' => 'US',
]));
