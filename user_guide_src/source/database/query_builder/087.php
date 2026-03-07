<?php

declare(strict_types=1);

use App\Libraries\MyClass;

$object = new MyClass();
$builder->set($object);
$builder->insert();
