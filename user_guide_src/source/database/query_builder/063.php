<?php

declare(strict_types=1);

$builder->havingLike('title', 'match');
$builder->orHavingLike('body', $match);
// HAVING `title` LIKE '%match%' ESCAPE '!' OR  `body` LIKE '%match%' ESCAPE '!'
