<?php
require_once 'includes/auth.php';
session_destroy();
redirect(SITE_URL . '/login.php');
