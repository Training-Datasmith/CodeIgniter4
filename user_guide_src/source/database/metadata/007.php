<?php

declare(strict_types=1);

$db = db_connect();

$query  = $db->query('YOUR QUERY');
$fields = $query->getFieldData();
