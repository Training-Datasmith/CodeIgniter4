<?php

declare(strict_types=1);

$table->setFooting('Subtotal', $subtotal, $notes); // or

$table->setFooting(['Subtotal', $subtotal, $notes]);
