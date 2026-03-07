<?php

declare(strict_types=1);

$users = $userModel->paginate(10, 'group1', null, $segment);
