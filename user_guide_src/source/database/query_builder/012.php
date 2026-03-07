<?php

declare(strict_types=1);

$builder->selectAvg('age');
$query = $builder->get();
// Produces: SELECT AVG(age) as age FROM mytable
