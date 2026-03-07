<?php

declare(strict_types=1);

$link = [
    'href'  => 'css/printer.css',
    'rel'   => 'stylesheet',
    'type'  => 'text/css',
    'media' => 'print',
];

echo link_tag($link);
// <link href="http://site.com/css/printer.css" rel="stylesheet" type="text/css" media="print">
