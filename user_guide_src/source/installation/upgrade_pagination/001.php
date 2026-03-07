<?php

declare(strict_types=1);

$model = new \App\Models\UserModel();

$data = [
    'users' => $model->paginate(10),
    'pager' => $model->pager,
];

return view('users/index', $data);
