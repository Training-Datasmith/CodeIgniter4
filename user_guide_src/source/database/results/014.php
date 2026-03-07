<?php

declare(strict_types=1);

$query = $db->query('YOUR QUERY');

$rows = $query->getCustomResultObject(\App\Entities\User::class);

foreach ($rows as $row) {
    echo $row->id;
    echo $row->email;
    echo $row->lastLogin('Y-m-d');
}
