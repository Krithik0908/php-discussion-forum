<?php
session_start();
include 'db.php';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile_number'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id FROM users WHERE username=? OR email=? OR mobile_number=?");
    $stmt->bind_param("sss", $username, $email, $mobile);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $error = "Username, email, or mobile number already in use.";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, email, mobile_number, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $email, $mobile, $password);
        if ($stmt->execute()) {
            $success = "Registered successfully! <a href='login.php'>Login now</a>";
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
    --primary: #6366f1;
    --secondary: #ec4899;
    --background: #f0f4ff;
    --text: #1e1e2f;
    --input-border: #d1d5db;
    --error-color: #e02424;
    --success-color: #16a34a;
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

.main {
    background: #fff;
    padding: 40px 45px;
    border-radius: 14px;
    box-shadow: 0 8px 30px rgba(99, 102, 241, 0.15);
    width: 100%;
    max-width: 420px;
    box-sizing: border-box;
    transition: box-shadow 0.3s ease, transform 0.3s ease;
}

.main:hover {
    box-shadow: 0 12px 45px rgba(99, 102, 241, 0.25);
    transform: translateY(-8px) scale(1.03);
}

h2 {
    margin-bottom: 28px;
    color: var(--primary);
    font-weight: 700;
    font-size: 28px;
    text-align: center;
}

input[type="text"],
input[type="email"],
input[type="password"] {
    width: 100%;
    padding: 14px 15px;
    margin-bottom: 24px;
    border: 2px solid var(--input-border);
    border-radius: 10px;
    font-size: 16px;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
    outline-offset: 2px;
    color: var(--text);
    background-color: #fafafa;
    box-sizing: border-box;
}

input[type="text"]:focus,
input[type="email"]:focus,
input[type="password"]:focus {
    border-color: var(--primary);
    box-shadow: 0 0 10px rgba(99, 102, 241, 0.4);
    background-color: white;
}

button {
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
}

button:hover {
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

p[style*="color:red"] {
    color: var(--error-color);
    font-weight: 600;
    margin-bottom: 18px;
}

p[style*="color:green"] {
    color: var(--success-color);
    font-weight: 600;
    margin-bottom: 18px;
}
</style>
    
</head>
<body>
<div class="main">
    <h2>Register</h2>
    <?php if ($error): ?><p style="color:red;"><?= $error ?></p><?php endif; ?>
    <?php if ($success): ?><p style="color:green;"><?= $success ?></p><?php endif; ?>

    <form method="POST" action="">
        <input type="text" name="username" placeholder="Username" required><br><br>
        <input type="email" name="email" placeholder="Email" required><br><br>
        <input type="text" name="mobile_number" placeholder="Mobile Number" required><br><br>
        <input type="password" name="password" placeholder="Password" required><br><br>
        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login</a></p>
</div>
</body>
</html>
