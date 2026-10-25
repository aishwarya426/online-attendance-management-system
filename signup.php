<?php

declare(strict_types=1);

// Public signup is disabled: accounts are created by an administrator
// (admin dashboard -> Add Student / Add Teacher). Nothing is processed here.

require_once __DIR__ . '/helpers.php';

redirect('index.php');
