<?php

declare(strict_types=1);

$names = ['Frank', 'Todd', 'James'];
$builder->whereNotIn('username', $names);
// Produces: WHERE username NOT IN ('Frank', 'Todd', 'James')
