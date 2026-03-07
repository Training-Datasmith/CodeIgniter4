<?php

declare(strict_types=1);

try {
    directory_mirror($uploadedImages, FCPATH . 'images/');
} catch (\Throwable $e) {
    echo 'Failed to export uploads!';
}
