<?php

declare(strict_types=1);

// get the CSP instance
$csp = $this->response->getCSP();

$csp->clearDirective('style-src');
