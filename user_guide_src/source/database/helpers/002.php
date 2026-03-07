<?php

declare(strict_types=1);

echo $db->table('my_table')->like('title', 'match')->countAllResults();
// Produces an integer, like 5
