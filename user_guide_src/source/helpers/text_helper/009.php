<?php

declare(strict_types=1);

$string = 'Fred, Bill,, Joe, Jimmy';
$string = reduce_multiples($string); // results in "Fred, Bill, Joe, Jimmy"
