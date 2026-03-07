<?php

declare(strict_types=1);

$image->withFile('/path/to/image/mypic.jpg')
    ->reorient()
    ->rotate(90)
    ->crop(100, 100, 0, 0)
    ->save('/path/to/image/mypic_thumb.jpg');
