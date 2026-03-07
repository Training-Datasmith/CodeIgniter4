<?php

declare(strict_types=1);

$js = ['onClick' => 'some_function();'];
echo form_checkbox('newsletter', 'accept', true, $js);
