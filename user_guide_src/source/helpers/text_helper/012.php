<?php

declare(strict_types=1);

$string = "Joe's \"dinner\"";
$string = strip_quotes($string); // results in "Joes dinner"
