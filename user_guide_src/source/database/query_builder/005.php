<?php

declare(strict_types=1);

$sql = $builder->getCompiledSelect();
echo $sql;
// Prints string: SELECT * FROM mytable
