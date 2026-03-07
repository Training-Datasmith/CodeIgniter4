<?php

declare(strict_types=1);

$request->getCookie('some_cookie');
$request->getCookie('some_cookie', FILTER_SANITIZE_FULL_SPECIAL_CHARS); // with filter
