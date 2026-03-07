<?php

declare(strict_types=1);

$builder->having(['title =' => 'My Title', 'id <' => $id]);
// Produces: HAVING title = 'My Title', id < 45
