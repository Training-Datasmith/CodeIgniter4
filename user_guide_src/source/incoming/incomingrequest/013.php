<?php

declare(strict_types=1);

var_dump($request->getRawInput());

/*
 * Outputs:
 * [
 *     'Param1' => 'Value1',
 *     'Param2' => 'Value2',
 * ]
 */
