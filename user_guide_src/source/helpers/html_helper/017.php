<?php

declare(strict_types=1);

echo object('movie.swf', 'application/x-shockwave-flash', 'class="test"');

echo object(
    'movie.swf',
    'application/x-shockwave-flash',
    'class="test"',
    [
        param('foo', 'bar', 'ref', 'class="test"'),
        param('hello', 'world', 'ref', 'class="test"'),
    ],
);
