<?php

declare(strict_types=1);

echo $builder->countAllResults(); // Produces an integer, like 25
$builder->like('title', 'match');
$builder->from('my_table');
echo $builder->countAllResults(); // Produces an integer, like 17
