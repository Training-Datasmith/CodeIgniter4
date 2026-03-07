<?php

declare(strict_types=1);

$query = $db->query('YOUR QUERY');

$row = $query->getRow();

if (isset($row)) {
    echo $row->title;
    echo $row->name;
    echo $row->body;
}
