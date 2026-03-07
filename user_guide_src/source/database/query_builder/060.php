<?php

declare(strict_types=1);

$builder->havingLike('title', 'match');
$builder->havingLike('body', 'match');
// HAVING `title` LIKE '%match%' ESCAPE '!' AND  `body` LIKE '%match% ESCAPE '!'
