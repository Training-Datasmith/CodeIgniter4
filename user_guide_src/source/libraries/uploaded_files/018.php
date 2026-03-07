<?php

declare(strict_types=1);

if ($file->isValid() && ! $file->hasMoved()) {
    $file->move($path);
}
