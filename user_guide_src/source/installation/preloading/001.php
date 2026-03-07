<?php

declare(strict_types=1);

// ...

class preload
{
    /**
     * @var array Paths to preload.
     */
    private array $paths = [
        [
            'include' => __DIR__ . '/system', // <== change this line to where CI is installed
            // ...
        ],
    ];

    // ...
}

// ...
