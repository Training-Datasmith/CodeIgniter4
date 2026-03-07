<?php

declare(strict_types=1);

if ($query->hasError()) {
    echo 'Code: ' . $query->getErrorCode();
    echo 'Error: ' . $query->getErrorMessage();
}
