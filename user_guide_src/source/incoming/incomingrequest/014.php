<?php

declare(strict_types=1);

$email = $request->getPost('email', FILTER_SANITIZE_EMAIL);
