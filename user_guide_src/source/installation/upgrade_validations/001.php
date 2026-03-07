<?php

declare(strict_types=1);

$isValid = $this->validateData($data, [
    'name'  => 'required|min_length[3]',
    'email' => 'required|valid_email',
    'phone' => 'required|numeric|max_length[10]',
]);
