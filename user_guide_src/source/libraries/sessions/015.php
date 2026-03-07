<?php

declare(strict_types=1);

/**
 * [hobbies] => Array
 *     (
 *         [music] => rock
 *         [sport] => running
 *     )
 */
$session->set('hobbies', [
    'music' => 'rock',
    'sport' => 'running',
]);

/**
 * [hobbies] => Array
 *     (
 *         [food] =>  cooking
 *         [music] => rock
 *         [sport] => tennis
 *     )
 */
$session->push('hobbies', [
    'food'  => 'cooking',
    'sport' => 'tennis',
]);
