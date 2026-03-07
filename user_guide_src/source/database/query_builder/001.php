<?php

declare(strict_types=1);

$db      = \Config\Database::connect();
$builder = $db->table('users');
