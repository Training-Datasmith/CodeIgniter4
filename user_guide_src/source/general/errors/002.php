<?php

declare(strict_types=1);

try {
    $user = $userModel->find($id);
} catch (\Exception $e) {
    exit($e->getMessage());
}
