<?php

declare(strict_types=1);

$builder->selectSum('age');
$query = $builder->get();
// Produces: SELECT SUM(age) as age FROM mytable
