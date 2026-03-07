<?php

declare(strict_types=1);

// Produces: ALTER TABLE `tablename` DROP FOREIGN KEY `users_foreign`
$forge->dropForeignKey('tablename', 'users_foreign');
