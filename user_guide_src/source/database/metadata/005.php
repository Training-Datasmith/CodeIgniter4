<?php

declare(strict_types=1);

$db = db_connect();

if ($db->fieldExists('field_name', 'table_name')) {
    // some code...
}
