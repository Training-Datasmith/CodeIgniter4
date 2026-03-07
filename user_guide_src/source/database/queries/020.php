<?php

declare(strict_types=1);

if ($pQuery->close()) {
    echo 'Success!';
} else {
    echo 'Deallocation of prepared statements failed!';
}
