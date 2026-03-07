<?php

declare(strict_types=1);

if ($db->simpleQuery('YOUR QUERY')) {
    echo 'Success!';
} else {
    echo 'Query failed!';
}
