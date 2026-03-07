<?php

declare(strict_types=1);

$hidden = ['username' => 'Joe', 'member_id' => '234'];
echo form_open('email/send', '', $hidden);
