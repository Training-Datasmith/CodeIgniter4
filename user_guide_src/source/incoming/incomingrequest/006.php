<?php

declare(strict_types=1);

if (! $request->isSecure()) {
    force_https();
}
