<?php

declare(strict_types=1);

$subquery = $db->table('users');
$builder  = $db->table('jobs')->fromSubquery($subquery, 'alias');
$query    = $builder->get();
// Produces: SELECT * FROM `jobs`, (SELECT * FROM `users`) `alias`
