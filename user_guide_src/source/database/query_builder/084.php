<?php

declare(strict_types=1);

$builder->set('name', $name);
$builder->set('title', $title);
$builder->set('status', $status);
$builder->insert();
