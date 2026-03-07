<?php

declare(strict_types=1);

$builder->where('id', $id);
$builder->delete();
/*
 * Produces:
 * DELETE FROM mytable
 * WHERE id = $id
 */
