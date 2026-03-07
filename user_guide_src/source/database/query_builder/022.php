<?php

declare(strict_types=1);

$builder->where('name', $name);
$builder->where('title', $title);
$builder->where('status', $status);
// WHERE name = 'Joe' AND title = 'boss' AND status = 'active'
