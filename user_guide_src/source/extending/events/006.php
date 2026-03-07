<?php

declare(strict_types=1);

use CodeIgniter\Events\Events;

Events::trigger('some_event', $foo, $bar, $baz);

Events::on('some_event', static function ($foo, $bar, $baz) {
    // ...
});
