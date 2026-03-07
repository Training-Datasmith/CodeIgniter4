<?php

declare(strict_types=1);

$builder->notHavingLike('title', 'match');
// HAVING `title` NOT LIKE '%match% ESCAPE '!'
