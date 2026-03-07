<?php

declare(strict_types=1);

$array = [
    'name'   => $name,
    'title'  => $title,
    'status' => $status,
];

$builder->set($array);
$builder->insert();
