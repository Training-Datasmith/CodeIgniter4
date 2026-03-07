<?php

declare(strict_types=1);

// these are all equivalent
$host = $request->header('host');
$host = $request->header('Host');
$host = $request->header('HOST');
