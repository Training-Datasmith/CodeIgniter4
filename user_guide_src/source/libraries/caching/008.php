<?php

declare(strict_types=1);

// 'iterator' has a value of 6
$cache->decrement('iterator'); // 'iterator' is now 5
$cache->decrement('iterator', 2); // 'iterator' is now 3
