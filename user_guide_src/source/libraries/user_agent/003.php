<?php

declare(strict_types=1);

if ($agent->isBrowser('Safari')) {
    echo 'You are using Safari.';
} elseif ($agent->isBrowser()) {
    echo 'You are using a browser.';
}
