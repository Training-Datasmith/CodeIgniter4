<?php

declare(strict_types=1);

echo $uri->showPassword()->getUserInfo();   // user:password
$uri->showPassword(false);
