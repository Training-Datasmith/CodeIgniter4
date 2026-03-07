<?php

declare(strict_types=1);

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

class RecentPostsCell extends Cell
{
    protected $posts;

    public function mount(): void
    {
        $this->posts = model('PostModel')->orderBy('created_at', 'DESC')->findAll(10);
    }
}
