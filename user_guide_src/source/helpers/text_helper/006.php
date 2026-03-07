<?php

declare(strict_types=1);

$string = 'http://example.com//index.php';
echo reduce_double_slashes($string); // results in "http://example.com/index.php"
