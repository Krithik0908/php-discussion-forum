<?php
session_start();
include 'db.php';

if (!isset($_SESSION['reset_user'])) {
    header("Location: login.php");
    exit();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass !== $confirm_pass) {
        $message = "Passwords do not match.";
    } elseif (strlen($new_pass) < 6) {
        $message = "Password must be at least 6 characters.";
    } else {
        $identifier = $_SESSION['reset_user'];
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ? OR email = ? OR mobile_number = ?");
        $stmt->bind_param("ssss", $new_pass, $identifier, $identifier, $identifier);
        if ($stmt->execute()) {
            unset($_SESSION['reset_user']);
            header("Location: login.php?reset=success");
            exit();
        } else {
            $message = "Failed to reset password.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <style>
                :root {
            --primary: #6366f1;
            --secondary: #ec4899;
            --background: #f0f4ff;
            --text: #1e1e2f;
            --input-border: #d1d5db;
            --error-color: #e02424;
        }
        body {
            background: var(--background);
            font-family: 'Poppins', sans-serif;
            margin: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text);
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            background: #fff;
            padding: 40px 45px;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(99, 102, 241, 0.15);
        }
        h2 {
            margin-bottom: 28px;
            text-align: center;
            color: var(--primary);
            font-weight: 700;
            font-size: 28px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 14px 15px;
            margin: 12px 0 24px 0;
            border: 2px solid var(--input-border);
            border-radius: 10px;
            font-size: 16px;
            color: var(--text);
            background-color: #fafafa;
        }
        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.4);
            background-color: white;
        }
        button[type="submit"] {
            width: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
            padding: 14px 0;
            border: none;
            border-radius: 10px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
        }
        button:hover {
            background: linear-gradient(90deg, #524ddf, #da3f92);
        }
        .error {
            color: var(--error-color);
            text-align: center;
            margin-bottom: 18px;
            font-weight: 600;
            font-size: 14px;
        }
        p {
            text-align: center;
            font-size: 15px;
            color: var(--text);
        }
        p a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Reset Password</h2>
        <?php if ($message): ?>
            <div class="error"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <button type="submit">Reset Password</button>
        </form>
        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>
