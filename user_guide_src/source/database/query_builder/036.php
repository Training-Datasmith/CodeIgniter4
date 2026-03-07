<?php

declare(strict_types=1);

$names = ['Frank', 'Todd', 'James'];
$builder->orWhereNotIn('username', $names);
// Produces: OR username NOT IN ('Frank', 'Todd', 'James')
