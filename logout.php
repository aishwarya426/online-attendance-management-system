<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

start_session();
$_SESSION = [];
session_destroy();
redirect('index.php');
