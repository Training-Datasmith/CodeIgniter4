<?php

declare(strict_types=1);

// In Controller.
if (! $this->validateData($data, $rules)) {
    return redirect()->back()->withInput();
}
