<?php

declare(strict_types=1);

$builder->havingLike('title', 'match');
$builder->orNotHavingLike('body', 'match');
// HAVING `title` LIKE '%match% OR  `body` NOT LIKE '%match%' ESCAPE '!'
