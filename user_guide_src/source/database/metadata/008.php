<?php

declare(strict_types=1);

$db = db_connect();

$keys = $db->getIndexData('table_name');

foreach ($keys as $key) {
    echo $key->name;
    echo $key->type;
    echo $key->fields; // array of field names
}
