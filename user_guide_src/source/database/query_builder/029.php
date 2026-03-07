<?php

declare(strict_types=1);

$builder->where('name !=', $name);
$builder->orWhere('id >', $id);
// Produces: WHERE name != 'Joe' OR id > 50
