<?php

declare(strict_types=1);

$dbutil = \Config\Database::utils();

if ($dbutil->databaseExists('database_name')) {
    // some code...
}
