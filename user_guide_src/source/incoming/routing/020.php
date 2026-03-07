<?php

declare(strict_types=1);

use App\Libraries\RSSFeeder;

$routes->get('feed', static function () {
    $rss = new RSSFeeder();

    return $rss->feed('general');
});
