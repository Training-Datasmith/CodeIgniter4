<?php

declare(strict_types=1);

// In app/Config/Events.php

namespace Config;

use CodeIgniter\Events\Events;

// ...

Events::on(
    'DBQuery',
    static function (\CodeIgniter\Database\Query $query) {
        log_message('info', (string) $query);
    },
);
