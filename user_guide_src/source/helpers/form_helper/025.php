<?php

declare(strict_types=1);

$data = [
    'name'    => 'newsletter',
    'id'      => 'newsletter',
    'value'   => 'accept',
    'checked' => true,
    'style'   => 'margin:10px',
];

echo form_checkbox($data);
// Would produce: <input type="checkbox" name="newsletter" id="newsletter" value="accept" checked="checked" style="margin:10px">
