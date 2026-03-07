<?php

declare(strict_types=1);

use CodeIgniter\Files\FileCollection;

$files = new FileCollection([
    FCPATH . 'index.php',
    ROOTPATH . 'spark',
]);
$files->addDirectory(APPPATH . 'Filters');
