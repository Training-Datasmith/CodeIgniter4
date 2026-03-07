<?php

declare(strict_types=1);

$user    = $userModel->find(15);
$options = $user->options;

$options['foo'] = 'bar';

$user->options = $options;
$userModel->save($user);
