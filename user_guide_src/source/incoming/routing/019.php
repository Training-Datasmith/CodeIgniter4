<?php

declare(strict_types=1);

$routes->get('login/(.+)', 'Auth::login/$1');
