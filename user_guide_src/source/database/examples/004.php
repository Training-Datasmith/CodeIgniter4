<?php

declare(strict_types=1);

$query = $db->query('SELECT name FROM my_table LIMIT 1');
$row   = $query->getRow();
echo $row->name;
