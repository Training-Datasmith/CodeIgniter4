<?php

declare(strict_types=1);

$dbutil = \Config\Database::utils();

$dbs = $dbutil->listDatabases();

foreach ($dbs as $db) {
    echo $db;
}
