<?php

declare(strict_types=1);

use CodeIgniter\Cache\Handlers\BaseHandler;

$prefixedKey = BaseHandler::validateKey($key, $prefix);
