<?php

declare(strict_types=1);

$builder->selectCount('age');
$query = $builder->get();
// Produces: SELECT COUNT(age) as age FROM mytable
