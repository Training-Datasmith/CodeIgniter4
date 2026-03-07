<?php

declare(strict_types=1);

echo $db->table('my_table')->countAll();
// Produces an integer, like 25
