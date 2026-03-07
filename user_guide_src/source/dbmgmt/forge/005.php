<?php

declare(strict_types=1);

if ($forge->dropDatabase('my_db')) {
    echo 'Database deleted!';
}
