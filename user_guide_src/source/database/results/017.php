<?php

declare(strict_types=1);

$query = $db->query('SELECT * FROM my_table');

echo $query->getFieldCount();
