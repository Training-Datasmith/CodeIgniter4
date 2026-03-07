<?php

declare(strict_types=1);

use CodeIgniter\Events\Events;

Events::on('foo', static function ($arg) use (&$result) {
    $result = $arg;
});

Events::trigger('foo', 'bar');

$this->assertEventTriggered('foo');
