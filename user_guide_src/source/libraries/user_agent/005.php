<?php

declare(strict_types=1);

if ($agent->isReferral()) {
    echo $agent->getReferrer();
}
