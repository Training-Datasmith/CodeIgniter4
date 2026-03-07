<?php

declare(strict_types=1);

if ($forge->createDatabase('my_db')) {
    echo 'Database created!';
}
