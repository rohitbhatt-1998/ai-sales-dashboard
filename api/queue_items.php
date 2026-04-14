<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::check();
header('Content-Type: application/json');

$items = DB::fetchAll(
    'SELECT q.*, l.name as lead_name, l.phone
     FROM call_queue q
     JOIN leads l ON l.id = q.lead_id
     ORDER BY q.status ASC, q.created_at ASC
     LIMIT 100'
);

jsonResponse(['success' => true, 'data' => $items]);
