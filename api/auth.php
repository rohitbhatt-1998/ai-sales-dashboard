<?php
// ============================================================
// API: Authentication (login / logout / me)
// ============================================================
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Response.php';

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'login') {
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = trim($body['email'] ?? '');
    $pass  = $body['password'] ?? '';

    if (!$email || !$pass) {
        Response::error('Email and password are required');
    }

    $result = Auth::login($email, $pass);
    if ($result['success']) {
        Response::success($result['user'], 'Login successful');
    } else {
        Response::error($result['error'], 401);
    }
}

if ($method === 'POST' && $action === 'logout') {
    Auth::logout();
    Response::success([], 'Logged out');
}

if ($method === 'GET' && $action === 'me') {
    Auth::requireLogin();
    Response::success(Auth::currentUser());
}

Response::error('Unknown action', 404);
