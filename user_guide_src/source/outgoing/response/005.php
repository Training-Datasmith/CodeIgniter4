<?php

declare(strict_types=1);

$this->response->setHeader('Cache-Control', 'no-cache')
    ->appendHeader('Cache-Control', 'must-revalidate');
