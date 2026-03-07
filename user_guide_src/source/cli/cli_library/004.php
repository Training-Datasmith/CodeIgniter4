<?php

declare(strict_types=1);

use CodeIgniter\CLI\CLI;

$overwrite = CLI::prompt('File exists. Overwrite?', ['y', 'n']);
