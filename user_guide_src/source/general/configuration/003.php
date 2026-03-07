<?php

declare(strict_types=1);

$config = config('Pager');
// Access settings as object properties
$pageSize = $config->perPage;
