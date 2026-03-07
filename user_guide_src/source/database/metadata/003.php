<?php

declare(strict_types=1);

$db = db_connect();

$fields = $db->getFieldNames('table_name');

foreach ($fields as $field) {
    echo $field;
}
