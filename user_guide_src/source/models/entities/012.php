<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class User extends Entity
{
    protected $casts = [
        'is_banned'          => 'boolean',
        'is_banned_nullable' => '?boolean',
    ];
}
