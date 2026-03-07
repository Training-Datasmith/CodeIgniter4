<?php

declare(strict_types=1);

use CodeIgniter\Events\Events;

// This runs every request — the 'my_event' listener stacks up indefinitely
Events::on('pre_system', static function (): void {
    Events::on('my_event', 'MyClass::myMethod');
});
