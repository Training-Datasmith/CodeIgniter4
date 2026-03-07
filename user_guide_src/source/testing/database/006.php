<?php

declare(strict_types=1);

$username = $this->grabFromDatabase('users', 'username', ['email' => 'joe@example.com']);
