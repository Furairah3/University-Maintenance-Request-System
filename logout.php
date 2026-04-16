<?php
require_once __DIR__ . '/backend/includes/Auth.php';
Auth::initSession();
Auth::logout();
header('Location: login.php');
exit;
