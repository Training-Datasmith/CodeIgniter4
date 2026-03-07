<?php

declare(strict_types=1);

$image->withFile('/path/to/image/mypic.jpg')
    ->withResource()
    ->save('/path/to/image/my_low_quality_pic.jpg', 10);
