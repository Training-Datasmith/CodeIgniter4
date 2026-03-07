<?php

declare(strict_types=1);

$names = ['Frank', 'Todd', 'James'];
$builder->whereIn('username', $names);
// Produces: WHERE username IN ('Frank', 'Todd', 'James')
