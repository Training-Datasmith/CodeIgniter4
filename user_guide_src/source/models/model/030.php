<?php

declare(strict_types=1);

$fieldName              = 'name';
$fieldValidationMessage = [
    'required' => 'Your name is required here',
];
$model->setValidationMessage($fieldName, $fieldValidationMessage);
