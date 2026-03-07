<?php

declare(strict_types=1);

// Check if checkbox is checked with class of 'foo'
if ($results->seeCheckboxIsChecked('.foo')) {
    // ...
}

// Check if checkbox with id of 'bar' is checked
if ($results->seeCheckboxIsChecked('#bar')) {
    // ...
}
