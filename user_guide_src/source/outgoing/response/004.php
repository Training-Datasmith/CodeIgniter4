<?php

declare(strict_types=1);

$this->response->setHeader('Location', 'http://example.com')
    ->setHeader('WWW-Authenticate', 'Negotiate');
