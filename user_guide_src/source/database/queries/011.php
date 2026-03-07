<?php

declare(strict_types=1);

$search = '20% raise';
$sql    = "SELECT id FROM table WHERE column LIKE '%" . $db->escapeLikeString($search) . "%' ESCAPE '!'";
