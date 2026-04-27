<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Config\Auth;

Auth::logout();
header('Location: login.php');
exit;
