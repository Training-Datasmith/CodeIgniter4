<?php

declare(strict_types=1);

$userModel
    ->whereIn('id', [1, 2, 3])
    ->set(['active' => 1])
    ->update();
