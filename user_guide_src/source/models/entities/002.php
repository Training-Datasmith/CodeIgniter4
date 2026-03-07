<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'users';
    protected $allowedFields = [
        'username', 'email', 'password',
    ];
    protected $returnType    = \App\Entities\User::class;
    protected $useTimestamps = true;
}
