<?php

declare(strict_types=1);

$dbutil = \Config\Database::utils();

$result = $dbutil->optimizeDatabase();

if ($result !== false) {
    print_r($result);
}
