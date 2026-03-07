<?php

declare(strict_types=1);

// Accept-Encoding: gzip, deflate, sdch
echo 'Accept-Encoding: ' . $request->getHeaderLine('accept-encoding');
