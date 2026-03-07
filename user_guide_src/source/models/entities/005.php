<?php

declare(strict_types=1);

$data = $this->request->getPost();

$user = new \App\Entities\User($data);
$userModel->save($user);
