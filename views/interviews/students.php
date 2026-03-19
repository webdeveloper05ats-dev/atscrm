<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (function_exists('requireView')) {
    requireView('interviews/students');
}

redirect('index.php?page=interviews/schedule');
