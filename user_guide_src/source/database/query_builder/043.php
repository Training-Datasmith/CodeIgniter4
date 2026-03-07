<?php

declare(strict_types=1);

$builder->notLike('title', 'match'); // WHERE `title` NOT LIKE '%match% ESCAPE '!'
