<?php

declare(strict_types=1);

if ($agent->isMobile('iphone')) {
    echo view('iphone/home');
} elseif ($agent->isMobile()) {
    echo view('mobile/home');
} else {
    echo view('web/home');
}
