<?php

declare(strict_types=1);

$user = $userModel->insert($data);

return $this->respondCreated($user);
