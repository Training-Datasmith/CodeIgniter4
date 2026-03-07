<?php

declare(strict_types=1);

$names = ['Frank', 'Todd', 'James'];
$builder->orWhereIn('username', $names);
// Produces: OR username IN ('Frank', 'Todd', 'James')
