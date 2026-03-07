<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\View as BaseView;

class View extends BaseView
{
    public $plugins = [
        'foo' => ['\Some\Class::methodName'],
    ];

    // ...
}

// {+ foo +} inner content {+ /foo +}
