<?php

declare(strict_types=1);

$template = 'Hello, {firstname} {initials} {lastname}';
$data     = [
    'title'     => 'Mr',
    'firstname' => 'John',
    'lastname'  => 'Doe',
];

return $parser->setData($data)->renderString($template);
// Result: Hello, John {initials} Doe
