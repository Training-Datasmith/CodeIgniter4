<?php

declare(strict_types=1);

/*
 * Response body is this:
 * [
 *     'config' => ['key-a', 'key-b'],
 * ]
 */

// Is true
$result->assertJSONFragment(['config' => ['key-a']]);
