<?php

declare(strict_types=1);

$users = $userModel->where('active', 1)->findAll();
