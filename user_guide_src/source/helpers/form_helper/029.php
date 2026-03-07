<?php

declare(strict_types=1);

$attributes = [
    'class' => 'mycustomclass',
    'style' => 'color: #000;',
];

echo form_label('What is your Name', 'username', $attributes);
// Would produce:  <label for="username" class="mycustomclass" style="color: #000;">What is your Name</label>
