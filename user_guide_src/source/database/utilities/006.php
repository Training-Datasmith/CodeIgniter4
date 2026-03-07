<?php

declare(strict_types=1);

$dbutil = \Config\Database::utils();

if ($dbutil->optimizeTable('table_name')) {
    echo 'Success!';
}
