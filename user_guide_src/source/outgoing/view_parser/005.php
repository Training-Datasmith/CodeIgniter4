<?php

declare(strict_types=1);

$template = '<head><title>{blog_title}</title></head>';
$data     = ['blog_title' => 'My ramblings'];

return $parser->setData($data)->renderString($template);
// Result: <head><title>My ramblings</title></head>
