<?php

declare(strict_types=1);

// Produces: DROP TABLE `table_name` CASCADE
$forge->dropTable('table_name', false, true);
