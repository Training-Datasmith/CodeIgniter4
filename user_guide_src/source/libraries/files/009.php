<?php

declare(strict_types=1);

$newName = $file->getRandomName();
$file->move(WRITEPATH . 'uploads', $newName);
