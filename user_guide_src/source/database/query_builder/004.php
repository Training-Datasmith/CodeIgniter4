<?php

declare(strict_types=1);

$query = $builder->get();

foreach ($query->getResult() as $row) {
    echo $row->title;
}
