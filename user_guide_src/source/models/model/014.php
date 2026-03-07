<?php

declare(strict_types=1);

$deletedUsers = $userModel->onlyDeleted()->findAll();
