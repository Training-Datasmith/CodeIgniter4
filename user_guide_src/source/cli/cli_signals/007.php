<?php

declare(strict_types=1);

// Basic signal registration
$this->registerSignals();

// Register specific signals
$this->registerSignals([SIGTERM, SIGINT]);

// Register signals with custom method mapping
$this->registerSignals(
    [SIGTERM, SIGINT, SIGUSR1],
    [
        SIGTERM => 'handleGracefulShutdown',
        SIGUSR1 => 'handleReload',
    ],
);
