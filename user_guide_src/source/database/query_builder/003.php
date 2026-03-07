<?php

declare(strict_types=1);

$query = $builder->get(10, 20);
/*
 * Executes: SELECT * FROM mytable LIMIT 20, 10
 * (in MySQL. Other databases have slightly different syntax)
 */
