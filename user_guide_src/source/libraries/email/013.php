<?php

declare(strict_types=1);

foreach ($list as $name => $address) {
    $email->clear();

    $email->setTo($address);
    $email->setFrom('your@example.com');
    $email->setSubject('Here is your info ' . $name);
    $email->setMessage('Hi ' . $name . ' Here is the info you requested.');
    $email->send();
}
