<?php

declare(strict_types=1);

echo $message->header('Accept-Language');
/*
 * Outputs something like:
 * 'Accept-Language: en,en-US'
 */

echo $message->header('Accept-Language')->getValue();
/*
 * Outputs something like:
 * [
 *     'en',
 *     'en-US',
 * ]
 */

echo $message->header('Accept-Language')->getValueLine();
/*
 * Outputs something like:
 * en,en-US'
 */
