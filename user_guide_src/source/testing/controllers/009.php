<?php

declare(strict_types=1);

$results = $this->withUri('http://example.com/forums/categories')
    ->controller(\App\Controllers\ForumController::class)
    ->execute('showCategories');
