<?php

declare(strict_types=1);

$config              = new \Config\App();
$config->appTimezone = 'America/Chicago';

$results = $this->withConfig($config)
    ->controller(\App\Controllers\ForumController::class)
    ->execute('showCategories');
