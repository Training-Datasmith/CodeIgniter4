<?php

declare(strict_types=1);

$builder->select('title, content, date');
$query = $builder->get();
// Executes: SELECT title, content, date FROM mytable
