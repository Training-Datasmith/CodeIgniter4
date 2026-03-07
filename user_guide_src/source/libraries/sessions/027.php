<?php

declare(strict_types=1);

// 'item' will be erased after 300 seconds
$session->markAsTempdata('item', 300);
