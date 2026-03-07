<?php

declare(strict_types=1);

$body = json_encode(['foo' => 'bar']);

$results = $this->withBody($body)
    ->controller(\App\Controllers\ForumController::class)
    ->execute('showCategories');
