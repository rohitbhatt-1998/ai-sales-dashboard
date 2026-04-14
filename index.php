<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['logged_in'])) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
