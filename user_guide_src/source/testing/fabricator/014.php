<?php

declare(strict_types=1);

$user = $fabricator(null, true);

$this->assertIsNumeric($user->id);
$this->dontSeeInDatabase('user', ['id' => $user->id]);
