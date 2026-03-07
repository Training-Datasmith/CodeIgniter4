<?php

declare(strict_types=1);

$sql = "INSERT INTO table (title) VALUES('" . $db->escapeString($title) . "')";
