<?php

declare(strict_types=1);

$sql = 'SELECT * FROM some_table WHERE id IN ? AND status = ? AND author = ?';
$db->query($sql, [[3, 6], 'live', 'Rick']);
