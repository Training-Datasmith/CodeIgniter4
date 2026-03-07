<?php

declare(strict_types=1);

$array = ['name' => $name, 'title' => $title, 'status' => $status];
$builder->where($array);
// Produces: WHERE name = 'Joe' AND title = 'boss' AND status = 'active'
