<?php

declare(strict_types=1);

use CodeIgniter\CLI\CLI;

$totalSteps = count($tasks);
$currStep   = 1;

foreach ($tasks as $task) {
    CLI::showProgress($currStep++, $totalSteps);
    $task->run();
}

// Done, so erase it...
CLI::showProgress(false);
