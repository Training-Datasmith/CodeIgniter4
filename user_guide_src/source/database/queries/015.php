<?php

declare(strict_types=1);

if (! $db->simpleQuery('SELECT `example_field` FROM `example_table`')) {
    $error = $db->error(); // Has keys 'code' and 'message'
}
