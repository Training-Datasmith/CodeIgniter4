<?php

declare(strict_types=1);

$files->removeFile(APPPATH . 'Filters/DevelopToolbar.php');

$files->removePattern('#\.gitkeep#');
$files->retainPattern('*.php');
