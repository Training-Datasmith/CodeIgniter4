<?php

declare(strict_types=1);

$builder->like('title', 'match');
$builder->orNotLike('body', 'match');
// WHERE `title` LIKE '%match% OR  `body` NOT LIKE '%match%' ESCAPE '!'
