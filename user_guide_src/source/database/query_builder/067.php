<?php

declare(strict_types=1);

$builder->orderBy('title DESC, name ASC');
// Produces: ORDER BY `title` DESC, `name` ASC
