<?php

declare(strict_types=1);

$this->load->library('parser');

$data = [
    'blog_title'   => 'My Blog Title',
    'blog_heading' => 'My Blog Heading',
];

$this->parser
    ->parse('blog_template', $data);
