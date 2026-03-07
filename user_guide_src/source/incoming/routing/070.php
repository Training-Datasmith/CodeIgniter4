<?php

declare(strict_types=1);

// In app/Config/Routing.php
use CodeIgniter\Config\Routing as BaseRouting;

class Routing extends BaseRouting
{
    // ...
    public bool $multipleSegmentsOneParam = true;
    // ...
}
