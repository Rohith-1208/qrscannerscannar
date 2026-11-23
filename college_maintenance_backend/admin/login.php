<?php
// ==============================================================================
// Admin Web Dashboard - Login
// ==============================================================================

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

// If already logged in, redirect to dashboard
if (!empty($_SESSION['admin_user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT admin_id, name, email, password_hash FROM admins WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                // Set session
                $_SESSION['admin_user'] = [
                    'admin_id' => $admin['admin_id'],
                    'name'     => $admin['name'],
                    'email'    => $admin['email']
                ];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid email address or password.';
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>College Admin Login - Equipment Maintenance Management</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-body">
  <div class="login-card">
    <div class="login-header">
      <div class="logo-badge">QM</div>
      <h2>College Administrator</h2>
      <p>Equipment Maintenance Management System</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="form-group">
        <label for="email">Admin Email</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="admin@college.edu" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? 'admin@college.edu') ?>">
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required value="admin123">
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 10px; padding: 12px;">
        Sign In to Admin Dashboard
      </button>

      <div style="margin-top: 20px; font-size: 12px; color: #64748b; text-align: center;">
        Default credentials: <b>admin@college.edu</b> / <b>admin123</b>
      </div>
    </form>
  </div>
</body>
</html>
