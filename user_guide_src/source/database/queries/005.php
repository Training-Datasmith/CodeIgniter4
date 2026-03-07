<?php

declare(strict_types=1);

$db->setPrefix('newprefix_');
$db->prefixTable('tablename'); // outputs newprefix_tablename
