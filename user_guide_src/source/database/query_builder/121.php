<?php

declare(strict_types=1);

use App\Libraries\MyClass;

$object = new MyClass();
$builder->insert($object);
// Produces: INSERT INTO mytable (title, content, date) VALUES ('My Title', 'My Content', 'My Date')
