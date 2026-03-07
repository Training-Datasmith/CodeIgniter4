<?php

declare(strict_types=1);

$users = $userModel->where('status', 'active')
    ->orderBy('last_login', 'asc')
    ->findAll();
