<?php

declare(strict_types=1);

/*
 * With a request body of:
 * {
 *     "foo": "bar",
 *     "fizz": {
 *         "buzz": "baz"
 *     }
 * }
 */

$data = $request->getJsonVar('foo');
// $data = "bar"

$data = $request->getJsonVar('fizz.buzz');
// $data = "baz"
