<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\View as BaseView;

class View extends BaseView
{
    public $filters = [
        'str_repeat' => '\str_repeat',
    ];

    // ...
}
