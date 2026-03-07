<?php

declare(strict_types=1);

$builder->like('title', 'match');
$builder->orLike('body', $match);
// WHERE `title` LIKE '%match%' ESCAPE '!' OR  `body` LIKE '%match%' ESCAPE '!'
