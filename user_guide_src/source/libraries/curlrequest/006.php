<?php

declare(strict_types=1);

echo $response->getStatusCode();
echo $response->getBody();
echo $response->header('Content-Type');
$language = $response->negotiateLanguage(['en', 'fr']);
