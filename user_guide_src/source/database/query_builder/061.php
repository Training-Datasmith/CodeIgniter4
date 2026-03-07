<?php

declare(strict_types=1);

$builder->havingLike('title', 'match', 'before'); // Produces: HAVING `title` LIKE '%match' ESCAPE '!'
$builder->havingLike('title', 'match', 'after');  // Produces: HAVING `title` LIKE 'match%' ESCAPE '!'
$builder->havingLike('title', 'match', 'both');   // Produces: HAVING `title` LIKE '%match%' ESCAPE '!'
