<?php

declare(strict_types=1);

service('image', 'imagick')
    ->withFile('/path/to/image/mypic.jpg')
    ->flip('horizontal')
    ->save('/path/to/new/image.jpg');
