<?php

declare(strict_types=1);

use CodeIgniter\CLI\CLI;

CLI::write("fileA \t" . CLI::color('/path/to/file', 'white'), 'yellow');
