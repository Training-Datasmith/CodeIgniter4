<?php

declare(strict_types=1);

var_dump($request->headers());

/*
 * Outputs:
 * [
 *     'Host'          => CodeIgniter\HTTP\Header,
 *     'Cache-Control' => CodeIgniter\HTTP\Header,
 *     'Accept'        => CodeIgniter\HTTP\Header,
 * ]
 */
