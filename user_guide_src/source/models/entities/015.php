<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Widget extends Entity
{
    protected $casts = [
        'colors' => 'csv',
    ];
}
