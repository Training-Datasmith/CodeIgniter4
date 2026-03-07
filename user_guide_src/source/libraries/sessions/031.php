<?php

declare(strict_types=1);

$tempdata = ['newuser' => true, 'message' => 'Thanks for joining!'];
$session->setTempdata($tempdata, null, $expire);
