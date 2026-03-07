<?php

declare(strict_types=1);

$array = ['name !=' => $name, 'id <' => $id, 'date >' => $date];
$builder->where($array);
