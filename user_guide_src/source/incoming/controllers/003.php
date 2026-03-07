<?php

declare(strict_types=1);

if (! $this->request->isSecure()) {
    $this->forceHTTPS(31536000); // one year
}
