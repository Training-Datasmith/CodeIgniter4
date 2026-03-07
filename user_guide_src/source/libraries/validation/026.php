<?php

declare(strict_types=1);

$errors = $validation->getErrors();
/*
 * Produces:
 * [
 *     'field1' => 'error message',
 *     'field2' => 'error message',
 * ]
 */
