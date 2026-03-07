<?php

declare(strict_types=1);

$query = $db->getLastQuery();
echo (string) $query;
