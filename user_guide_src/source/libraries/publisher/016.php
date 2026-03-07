<?php

declare(strict_types=1);

use CodeIgniter\Publisher\Publisher;

$memePublishers = Publisher::discover('Publishers', 'Namespace\Vendor\Package');
