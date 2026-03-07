<?php

declare(strict_types=1);

$builder->set('name', $name);
$builder->insert();
// Produces: INSERT INTO mytable (`name`) VALUES ('{$name}')
