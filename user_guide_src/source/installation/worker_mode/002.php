<?php

declare(strict_types=1);

namespace Config;

class WorkerMode extends \CodeIgniter\Config\WorkerMode
{
    public array $resetEventListeners = ['my_event'];
}
