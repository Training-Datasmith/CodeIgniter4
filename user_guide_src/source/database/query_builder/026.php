<?php

declare(strict_types=1);

$where = "name='Joe' AND status='boss' OR status='active'";
$builder->where($where);
