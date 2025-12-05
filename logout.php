<?php
require_once 'includes/bootstrap.php';

$auth->logout();
redirect(APP_URL . '/index.php');
