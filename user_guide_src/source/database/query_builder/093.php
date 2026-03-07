<?php

declare(strict_types=1);

$builder->delete(['id' => $id]);
// Produces: DELETE FROM mytable WHERE id = $id
