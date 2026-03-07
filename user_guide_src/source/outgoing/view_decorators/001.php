<?php

declare(strict_types=1);

namespace App\Views\Decorators;

use CodeIgniter\View\ViewDecoratorInterface;

class MyDecorator implements ViewDecoratorInterface
{
    public static function decorate(string $html): string
    {
        // Modify the output here

        return $html;
    }
}
