<?php
require_once 'includes/bootstrap.php';

$auth->logout();
redirect(APP_URL . '/login.php');
