<?php

declare(strict_types=1);

$string = "Joe's \"dinner\"";
$string = quotes_to_entities($string); // results in "Joe&#39;s &quot;dinner&quot;"
