<?php

declare(strict_types=1);

// Public password recovery is disabled: anyone who knew an email address could take over the
// account. Passwords are reset by an administrator (Students / Teachers > Reset password).

require_once __DIR__ . '/helpers.php';

redirect('index.php');
