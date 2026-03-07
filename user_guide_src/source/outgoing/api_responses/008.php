<?php

declare(strict_types=1);

$user = $userModel->delete($id);

return $this->respondDeleted(['id' => $id]);
