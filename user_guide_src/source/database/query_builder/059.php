<?php

declare(strict_types=1);

$builder->havingLike('title', 'match');
// Produces: HAVING `title` LIKE '%match%' ESCAPE '!'
