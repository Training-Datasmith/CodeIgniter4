<?php

declare(strict_types=1);

$builder->select('(SELECT SUM(payments.amount) FROM payments WHERE payments.invoice_id=4) AS amount_paid', false);
$query = $builder->get();
