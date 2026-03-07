<?php

declare(strict_types=1);

// command line: php index.php users 21 profile --foo bar
echo $request->getOptions();  // ['foo' => 'bar']
