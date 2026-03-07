<?php

declare(strict_types=1);

if (! $request->isValidIP($ip)) {
    echo 'Not Valid';
} else {
    echo 'Valid';
}
