<?php

declare(strict_types=1);

$fabricator->setOverrides(['first' => 'Bobby'], $persist = false);
$bobbyUser = $fabricator->make();
$bobbyUser = $fabricator->make();
