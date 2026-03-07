<?php

declare(strict_types=1);

use App\Controllers\Blog;

$routes->get('blog', [Blog::class, 'index']);
