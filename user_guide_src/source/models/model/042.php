<?php

declare(strict_types=1);

$model->protect(false)
    ->insert($data)
    ->protect(true);
