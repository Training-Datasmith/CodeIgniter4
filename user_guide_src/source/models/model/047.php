<?php

declare(strict_types=1);

$users = $userModel->asArray()->where('status', 'active')->findAll();
