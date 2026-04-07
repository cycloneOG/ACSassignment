<?php
session_start();
require_once __DIR__ . '/utils.php';
logoutUser();
header('Location: login.php');
exit;
