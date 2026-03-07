<?php

declare(strict_types=1);

$builder->distinct();
$builder->get();
// Produces: SELECT DISTINCT * FROM mytable
