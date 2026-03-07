<?php

declare(strict_types=1);

$user = $userModel->where('deleted', 0)->first();
