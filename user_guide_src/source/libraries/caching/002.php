<?php

declare(strict_types=1);

$cache = service('cache');

$foo = $cache->get('foo');
