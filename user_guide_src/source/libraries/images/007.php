<?php

declare(strict_types=1);

$image = service('image');

try {
    $image->withFile('/path/to/image/mypic.jpg')
        ->fit(100, 100, 'center')
        ->save('/path/to/image/mypic_thumb.jpg');
} catch (\CodeIgniter\Images\Exceptions\ImageException $e) {
    echo $e->getMessage();
}
