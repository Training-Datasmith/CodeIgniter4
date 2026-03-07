<?php

declare(strict_types=1);

// In app/Config/Routes.php
// Would execute the show404 method of the App\Errors class
$routes->set404Override('App\Errors::show404');

// Will display a custom view.
$routes->set404Override(static function () {
    // If you want to get the URI segments.
    $segments = request()->getUri()->getSegments();

    return view('my_errors/not_found.html');
});
