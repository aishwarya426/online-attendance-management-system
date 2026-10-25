<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';

const ROLES = ['student', 'teacher', 'admin'];

$error_msg = null;

if (isset($_POST['login'])) {
    try {
        $username = post('username');
        $password = post('password', false);
        $type     = post('type');

        if ($username === '') {
            throw new InvalidArgumentException('Username is required!');
        }
        if ($password === '') {
            throw new InvalidArgumentException('Password is required!');
        }

        $user = null;
        if (in_array($type, ROLES, true)) {
            $stmt = $pdo->prepare('SELECT id, password FROM admininfo WHERE username = ? AND type = ?');
            $stmt->execute([$username, $type]);
            foreach ($stmt->fetchAll() as $row) {
                if (password_matches($password, $row['password'])) {
                    $user = $row;
                    break;
                }
            }
        }

        if ($user === null) {
            throw new InvalidArgumentException('Username, Password or Role is wrong, try again!');
        }

        // Deactivated account, or a student/teacher login without a profile
        $account = load_account($pdo, (int) $user['id']);
        $problem = $account === null ? 'Account not found.' : account_problem($account);
        if ($problem !== null) {
            throw new InvalidArgumentException($problem);
        }

        // Upgrade old plaintext passwords (or outdated hashes) on successful login.
        if (password_is_legacy($user['password']) || password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $upgrade = $pdo->prepare('UPDATE admininfo SET password = ? WHERE id = ?');
            $upgrade->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        start_session();
        session_regenerate_id(true);
        $_SESSION['uid']  = (int) $user['id'];
        $_SESSION['role'] = $type;

        redirect($type . '/index.php');
    } catch (InvalidArgumentException $ex) {
        $error_msg = $ex->getMessage();
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        $error_msg = 'Something went wrong. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Online Attendance Management System 1.0</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap-theme.min.css">
  <link rel="stylesheet" href="css/main.css?v=<?= (int) @filemtime(__DIR__ . '/css/main.css') ?>">
</head>
<body class="login-page">
<main class="login-wrap">
  <div class="login-card">
    <div class="login-brand">
      <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-4"/></svg></span>
      <h1>Online Attendance Management System <span class="ver">1.0</span></h1>
      <p class="login-sub">Sign in to continue</p>
    </div>

    <?php if ($error_msg !== null): ?>
      <p class="msg-err"><?= e($error_msg) ?></p>
    <?php endif; ?>

    <form method="post" class="login-form">
      <div class="form-group">
        <label for="username" class="control-label">Username</label>
        <input type="text" name="username" class="form-control" id="username" placeholder="your username" autocomplete="username">
      </div>

      <div class="form-group">
        <label for="password" class="control-label">Password</label>
        <input type="password" name="password" class="form-control" id="password" placeholder="your password" autocomplete="current-password">
      </div>

      <div class="form-group">
        <span class="control-label">Role</span>
        <div class="role-choice">
          <label><input type="radio" name="type" value="student" checked><span>Student</span></label>
          <label><input type="radio" name="type" value="teacher"><span>Teacher</span></label>
          <label><input type="radio" name="type" value="admin"><span>Admin</span></label>
        </div>
      </div>

      <input type="submit" class="btn btn-primary btn-block" value="Login" name="login">
    </form>

    <p class="login-help">Forgot your password? Please contact your administrator.</p>
  </div>
</main>
</body>
</html>
