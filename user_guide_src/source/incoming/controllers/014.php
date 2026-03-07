<?php

declare(strict_types=1);

namespace App\Controllers;

class Products extends BaseController
{
    public function shoes($sandals, $id)
    {
        return $sandals . $id;
    }
}
