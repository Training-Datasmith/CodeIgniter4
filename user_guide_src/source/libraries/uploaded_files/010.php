<?php

declare(strict_types=1);

if (! $file->isValid()) {
    throw new \RuntimeException($file->getErrorString() . '(' . $file->getError() . ')');
}
