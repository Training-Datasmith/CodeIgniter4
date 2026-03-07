<?php

declare(strict_types=1);

$client->request('GET', 'http://example.com', ['cookie' => WRITEPATH . 'CookieSaver.txt']);
