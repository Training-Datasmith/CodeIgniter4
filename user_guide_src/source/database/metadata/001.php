<?php

declare(strict_types=1);

$db = db_connect();

$tables = $db->listTables();

foreach ($tables as $table) {
    echo $table;
}
