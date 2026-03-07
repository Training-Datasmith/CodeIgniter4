<?php

declare(strict_types=1);

$results = $this->controller(\App\Controllers\ForumController::class)
    ->execute('showCategories');
