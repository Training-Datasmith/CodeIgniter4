<?php

declare(strict_types=1);

use CodeIgniter\Database\RawSql;

$sql = "id > 2 AND name != 'Accountant'";
$builder->where(new RawSql($sql));
