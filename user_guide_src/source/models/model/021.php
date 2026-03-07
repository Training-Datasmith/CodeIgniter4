<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class JobModel extends Model
{
    protected $table         = 'jobs';
    protected $returnType    = \App\Entities\Job::class;
    protected $allowedFields = [
        'name', 'description',
    ];
}
