<?php

// Include bootstrap file
require_once __DIR__ . '/../includes/bootstrap.php';

if (!in_array($_ENV['APP_ENV'], ['local'])) exit;

// Write playground code from this point forward
