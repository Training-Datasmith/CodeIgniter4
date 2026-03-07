<?php

declare(strict_types=1);

foreach ($userAccounts as $user) {
    $validation->reset();
    $validation->setRules($userAccountRules);

    if (! $validation->run($user)) {
        // handle validation errors
    }
}
