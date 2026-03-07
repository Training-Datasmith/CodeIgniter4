<?php

declare(strict_types=1);

$name  = $builder->db->escape('Joe');
$where = "name={$name} AND status='boss' OR status='active'";
$builder->where($where);
