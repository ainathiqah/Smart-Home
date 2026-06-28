<?php
session_start();
require_once "config/db_config.php";

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Please fill in all fields.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $conn = getDB();

        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $error = "Username already taken.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hash);
            $stmt->execute();
            $userId = $stmt->insert_id;
            $stmt->close();

            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            header("Location: add_room.php");
            exit;
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register - Smart Home</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . "/style.css") ?>">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="auth-logo"><i class="fa-solid fa-house-signal"></i></div>
    <h1>Create Account</h1>
    <p>Smart Home Environment System</p>
    <?php if ($error): ?><div class="login-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
      <input type="password" name="password" placeholder="Password (min 6 characters)" required>
      <input type="password" name="confirm_password" placeholder="Confirm Password" required>
      <button type="submit">Create Account</button>
    </form>
    <p style="margin-top:16px;font-size:13px;color:#5c6b85;">Already have an account? <a href="login.php" style="color:#2d6ee6;font-weight:600;text-decoration:none;">Log in</a></p>
  </div>
</div>
</body>
</html>
