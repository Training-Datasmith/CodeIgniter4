<?php

declare(strict_types=1);

$sql = 'INSERT INTO mytable (title, name) VALUES (' . $db->escape($title) . ', ' . $db->escape($name) . ')';
$db->query($sql);
echo $db->affectedRows();
