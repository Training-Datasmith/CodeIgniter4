<?php

declare(strict_types=1);

// Make sure users are logged in before checking their account
$this->assertFilter('users/account', 'before', 'login');
