<?php

declare(strict_types=1);

$builder->like('title', 'match');
// Produces: WHERE `title` LIKE '%match%' ESCAPE '!'
