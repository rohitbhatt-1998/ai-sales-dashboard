<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install — AI Sales Dashboard</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#1e293b;border:1px solid #334155;border-radius:16px;padding:40px;width:100%;max-width:520px}
h1{font-size:24px;font-weight:700;color:#f8fafc;margin-bottom:4px}
p.sub{color:#94a3b8;font-size:14px;margin-bottom:28px}
label{display:block;font-size:13px;font-weight:600;color:#94a3b8;margin-bottom:6px;margin-top:16px}
input{width:100%;background:#0f172a;border:1px solid #334155;color:#f1f5f9;padding:10px 14px;border-radius:8px;font-size:14px;outline:none;transition:border-color .2s}
input:focus{border-color:#6366f1}
.btn{margin-top:24px;width:100%;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;padding:12px;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer}
.btn:hover{opacity:.9}
.alert{padding:12px 16px;border-radius:8px;font-size:14px;margin-top:16px}
.alert.success{background:#052e16;border:1px solid #166534;color:#86efac}
.alert.error{background:#2d1515;border:1px solid #7f1d1d;color:#fca5a5}
.sep{border-top:1px solid #334155;margin:24px 0}
h2{font-size:16px;font-weight:600;color:#e2e8f0;margin-bottom:4px}
</style>
</head>
<body>
<div class="card">
<h1>🤖 AI Sales Dashboard</h1>
<p class="sub">First-time setup — configure your database and admin account</p>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost   = trim($_POST['db_host']   ?? 'localhost');
    $dbName   = trim($_POST['db_name']   ?? '');
    $dbUser   = trim($_POST['db_user']   ?? '');
    $dbPass   = $_POST['db_pass']        ?? '';
    $adminName  = trim($_POST['admin_name']  ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = $_POST['admin_pass']       ?? '';

    $errors = [];
    if (!$dbName)      $errors[] = 'Database name is required';
    if (!$dbUser)      $errors[] = 'Database user is required';
    if (!$adminName)   $errors[] = 'Admin name is required';
    if (!$adminEmail)  $errors[] = 'Admin email is required';
    if (strlen($adminPass) < 6) $errors[] = 'Admin password must be at least 6 characters';

    if (empty($errors)) {
        // Write config
        $configContent = '<?php' . PHP_EOL
            . 'define(\'DB_HOST\',    \'' . addslashes($dbHost) . '\');' . PHP_EOL
            . 'define(\'DB_PORT\',    \'3306\');' . PHP_EOL
            . 'define(\'DB_NAME\',    \'' . addslashes($dbName) . '\');' . PHP_EOL
            . 'define(\'DB_USER\',    \'' . addslashes($dbUser) . '\');' . PHP_EOL
            . 'define(\'DB_PASS\',    \'' . addslashes($dbPass) . '\');' . PHP_EOL
            . 'define(\'DB_CHARSET\', \'utf8mb4\');' . PHP_EOL
            . 'define(\'APP_NAME\',   \'AI Sales Calling Dashboard\');' . PHP_EOL
            . 'define(\'APP_VERSION\',\'1.0.0\');' . PHP_EOL
            . 'define(\'APP_URL\',    \'http\' . (isset($_SERVER[\'HTTPS\']) ? \'s\' : \'\') . \'://\' . ($_SERVER[\'HTTP_HOST\'] ?? \'localhost\'));' . PHP_EOL
            . 'define(\'APP_SECRET\', \'' . bin2hex(random_bytes(24)) . '\');' . PHP_EOL
            . 'define(\'SESSION_LIFETIME\', 3600 * 8);' . PHP_EOL
            . 'define(\'BASE_PATH\', dirname(__DIR__));' . PHP_EOL
            . 'define(\'LOG_PATH\',  BASE_PATH . \'/logs\');' . PHP_EOL;

        try {
            file_put_contents(__DIR__ . '/config/db.php', $configContent);
        } catch (Exception $e) {
            $errors[] = 'Cannot write config file — check folder permissions: ' . $e->getMessage();
        }
    }

    if (empty($errors)) {
        // Try DB connection
        try {
            $pdo = new PDO(
                "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
                $dbUser, $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Run schema
            $sql = file_get_contents(__DIR__ . '/database/schema.sql');
            // Split on semicolons (naive but works for our schema)
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                if ($stmt) $pdo->exec($stmt);
            }

            // Create admin
            require_once __DIR__ . '/includes/Auth.php';
            Auth::createAdmin($adminName, $adminEmail, $adminPass);

            // Write lock file
            file_put_contents(__DIR__ . '/install.lock', date('c'));

            echo '<div class="alert success">
            ✅ Installation complete! <a href="index.php" style="color:#4ade80;font-weight:600;">Go to Dashboard →</a>
            <br><small style="opacity:.7">Delete or rename install.php for security.</small>
            </div>';
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        echo '<div class="alert error">';
        foreach ($errors as $err) echo '<div>• ' . htmlspecialchars($err) . '</div>';
        echo '</div>';
    }
}

// Block if already installed
if (file_exists(__DIR__ . '/install.lock')) {
    echo '<div class="alert error">Already installed. Delete <code>install.lock</code> to re-run.</div></div></body></html>';
    exit;
}
?>

<form method="POST">
<h2>Database</h2>
<label>Host</label>
<input name="db_host" value="localhost" placeholder="localhost">
<label>Database Name</label>
<input name="db_name" placeholder="ai_sales_db" required>
<label>Username</label>
<input name="db_user" placeholder="root" required>
<label>Password</label>
<input type="password" name="db_pass" placeholder="(leave blank if none)">

<div class="sep"></div>
<h2>Admin Account</h2>
<label>Full Name</label>
<input name="admin_name" placeholder="John Doe" required>
<label>Email</label>
<input type="email" name="admin_email" placeholder="admin@example.com" required>
<label>Password</label>
<input type="password" name="admin_pass" placeholder="Min 6 characters" required>

<button class="btn" type="submit">Install Now →</button>
</form>
</div>
</body>
</html>
