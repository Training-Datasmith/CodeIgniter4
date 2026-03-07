<?php

declare(strict_types=1);

// Check for AJAX request.
if ($request->isAJAX()) {
    // ...
}

// Check for CLI Request
if ($request->isCLI()) {
    // ...
}
