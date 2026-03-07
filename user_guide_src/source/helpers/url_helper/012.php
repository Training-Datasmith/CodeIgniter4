<?php

declare(strict_types=1);

$attributes = ['title' => 'Mail me'];
echo mailto('me@my-site.com', 'Contact Me', $attributes);
