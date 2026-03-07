<?php

declare(strict_types=1);

$builder = $db->table('mytable');
$query   = $builder->get();  // Produces: SELECT * FROM mytable
