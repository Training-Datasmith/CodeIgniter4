<?php

declare(strict_types=1);

use CodeIgniter\Events\Events;

Events::on('pre_system', ['MyClass', 'myFunction']);
