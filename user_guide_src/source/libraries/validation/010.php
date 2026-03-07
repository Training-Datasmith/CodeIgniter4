<?php

declare(strict_types=1);

// Fred Flintsone & Wilma
$validation->setRules([
    'contacts.friends.*.name' => 'required|max_length[60]',
]);
