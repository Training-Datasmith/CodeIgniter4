<?php

declare(strict_types=1);

$validation = service('validation');
$request    = service('request');

if ($validation->withRequest($request)->run()) {
    // If you use the input data, you should get it from the getValidated() method.
    // Otherwise you may create a vulnerability.
    $validData = $validation->getValidated();

    // ...
}
