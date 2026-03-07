<?php

declare(strict_types=1);

array_sort_by_multiple_keys($players, [
    'team.order' => SORT_ASC,
    'position'   => SORT_ASC,
]);
