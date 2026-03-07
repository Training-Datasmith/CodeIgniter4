<?php

declare(strict_types=1);

$data = [
    'title'   => 'My title',
    'heading' => 'My Heading',
    'message' => 'My Message',
];

return view('blog_view', $data, ['saveData' => false]);
