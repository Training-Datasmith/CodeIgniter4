<?php

declare(strict_types=1);

use CodeIgniter\Events\Events;

Events::on('post_controller_constructor', 'some_function', 25);
