<?php

declare(strict_types=1);

if (! $validation->run($data)) {
    // handle validation errors
}
// or
if (! $validation->run($data, 'signup')) {
    // handle validation errors
}
