<?php

declare(strict_types=1);

cookies()->display(); // array of Cookie objects

// or even from the Response
service('response')->getCookies();
