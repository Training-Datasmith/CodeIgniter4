<?php

declare(strict_types=1);

$string = ',Fred, Bill,, Joe, Jimmy,';
$string = reduce_multiples($string, ',', true); // results in "Fred, Bill, Joe, Jimmy"
