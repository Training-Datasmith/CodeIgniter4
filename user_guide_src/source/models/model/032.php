<?php

declare(strict_types=1);

if ($model->save($data) === false) {
    return view('updateUser', ['errors' => $model->errors()]);
}
