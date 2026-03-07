<?php

declare(strict_types=1);

service('image', 'imagick')
    ->withFile('/path/to/image/mypic.jpg')
    ->clearMetadata()
    ->save('/path/to/new/image.jpg');
