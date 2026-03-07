<?php

declare(strict_types=1);

$path = $this->request->getFile('userfile')->store('head_img/', 'user_name.jpg');
