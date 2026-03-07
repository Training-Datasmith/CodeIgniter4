<?php

declare(strict_types=1);

$builder = $db->table('mytable');

$query = $builder->select('title')
    ->where('id', $id)
    ->limit(10, 20)
    ->get();
