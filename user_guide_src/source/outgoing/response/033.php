<?php

declare(strict_types=1);

$data = 'Here is some text!';
$name = 'mytext.txt';

return $this->response->download($name, $data)->inline();
