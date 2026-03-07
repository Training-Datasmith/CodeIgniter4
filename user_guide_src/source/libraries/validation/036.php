<?php

declare(strict_types=1);

$validation->setRules([
    'foo' => 'required|max_length[19]|even',
]);
