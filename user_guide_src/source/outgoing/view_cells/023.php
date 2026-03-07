<?php

declare(strict_types=1);

// app/Cells/AlertMessageCell.php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

class AlertMessageCell extends Cell
{
    public string $type    = '';
    public string $message = '';
}
