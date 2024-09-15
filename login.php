<?php
session_start();
include 'db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = $_POST['username'];
    $password = $_POST['password'];
    $selected_role = $_POST['role'] ?? '';

    if (!in_array($selected_role, ['user', 'admin'])) {
        $error = "Invalid role selected!";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE (username = ? OR email = ? OR mobile_number = ?) AND is_active = 1");
        $stmt->bind_param("sss", $identifier, $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user['password'] === $password) {
                if ($user['role'] === $selected_role) {
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Incorrect role selection for this account.";
                }
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "User not found or is blocked!";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
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
            transition: box-shadow 0.3s ease, transform 0.3s ease;
        }

        .login-container:hover {
            box-shadow: 0 12px 45px rgba(99, 102, 241, 0.25);
            transform: translateY(-8px) scale(1.03);
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
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            outline-offset: 2px;
            color: var(--text);
            background-color: #fafafa;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.4);
            background-color: white;
        }

        .role-selection-radio {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
            gap: 20px;
        }

        .role-selection-radio label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            cursor: pointer;
            color: var(--text);
        }

        .role-selection-radio input[type="radio"] {
            accent-color: var(--primary);
            width: 18px;
            height: 18px;
            cursor: pointer;
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
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.3);
            transition: background 0.3s ease, box-shadow 0.3s ease;
            margin-top: 20px;
        }

        button[type="submit"]:hover {
            background: linear-gradient(90deg, #524ddf, #da3f92);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.5);
        }

        p {
            text-align: center;
            margin-top: 18px;
            font-size: 15px;
            color: var(--text);
        }

        p a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        p a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .error {
            color: var(--error-color);
            text-align: center;
            margin-bottom: 18px;
            font-weight: 600;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>

            <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
                <div style="color:green; text-align:center; margin-bottom: 18px;">Password has been reset successfully!</div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <input type="text" name="username" id="usernameInput" placeholder="Username / Email / Mobile" required>
            <input type="password" name="password" id="passwordInput" placeholder="Password" required>
            <p style="text-align:right; margin: -16px 0 16px;"><a href="forgot_password.php">Forgot Password?</a></p>

            <div class="role-selection-radio">
                <label><input type="radio" name="role" value="user"> <span>User</span></label>
                <label><input type="radio" name="role" value="admin"> <span>Admin</span></label>
            </div>

            <button type="submit">Login</button>
        </form>
        <p><a href="dashboard.php">Back to Dashboard</a></p>
        <p>New user? <a href="register.php">Register</a></p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const radios = document.querySelectorAll('input[name="role"]');
            const usernameInput = document.getElementById('usernameInput');
            const passwordInput = document.getElementById('passwordInput');

            radios.forEach(radio => {
                radio.addEventListener('change', () => {
                    if (radio.value === 'admin') {
                        usernameInput.value = 'admin';
                        passwordInput.value = 'admin@123';
                    } else if (radio.value === 'user') {
                        usernameInput.value = 'Krithik';
                        passwordInput.value = 'Kri@123';
                    }
                });
            });

            document.getElementById('loginForm').addEventListener('submit', function(event) {
                const selectedRole = document.querySelector('input[name="role"]:checked');
                if (!selectedRole) {
                    alert('Please select whether you are a User or an Admin.');
                    event.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
