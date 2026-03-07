<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class MyModel extends Model
{
    // ...

    protected $beforeInsert = ['hashPassword'];
    protected $beforeUpdate = ['hashPassword'];

    // ...
}
