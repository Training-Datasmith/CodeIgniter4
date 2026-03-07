<?php

declare(strict_types=1);

$db = db_connect();

if ($db->tableExists('table_name')) {
    // some code...
}
