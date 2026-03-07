<?php

declare(strict_types=1);

class MyRules
{
    public function even($value): bool
    {
        return (int) $value % 2 === 0;
    }
}
