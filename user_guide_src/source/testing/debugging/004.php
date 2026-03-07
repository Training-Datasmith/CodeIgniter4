<?php

declare(strict_types=1);

namespace MyNamespace;

use CodeIgniter\Debug\Toolbar\Collectors\BaseCollector;

class MyCollector extends BaseCollector
{
    protected $hasTimeline = false;

    protected $hasTabContent = false;

    protected $hasVarData = false;

    protected $title = '';
}
