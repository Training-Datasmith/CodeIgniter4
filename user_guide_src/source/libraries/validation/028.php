<?php

declare(strict_types=1);

if ($validation->hasError('username')) {
    echo $validation->getError('username');
}
