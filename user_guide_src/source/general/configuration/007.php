<?php

declare(strict_types=1);

namespace CodeIgniter\Shield\Config;

class Registrar
{
    public static function Pager(): array
    {
        return [
            'templates' => [
                'module_pager' => 'MyModule\Views\Pager',
            ],
        ];
    }
}
