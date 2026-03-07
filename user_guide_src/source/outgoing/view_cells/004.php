<?php

declare(strict_types=1);

namespace App\Cells;

class AlertMessage
{
    public function show(array $params): string
    {
        return "<div class=\"alert alert-{$params['type']}\">{$params['message']}</div>";
    }
}
