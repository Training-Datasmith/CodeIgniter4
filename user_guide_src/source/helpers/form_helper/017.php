<?php

declare(strict_types=1);

echo form_input('email', 'joe@example.com', ['placeholder' => 'Email Address...'], 'email');
/*
 * Would produce:
 * <input type="email" name="email" value="joe@example.com" placeholder="Email Address...">
 */
