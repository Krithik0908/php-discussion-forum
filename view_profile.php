<?php
include 'db.php';

if (!isset($_GET['username'])) {
    echo "User not specified.";
    exit();
}

$username = $_GET['username'];

$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo "User not found.";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($user['username']) ?>'s Profile</title>
    
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .profile-container {
            max-width: 400px;
            margin: auto;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        img {
            border-radius: 50%;
            width: 150px;
            height: 150px;
            object-fit: cover;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <h2><?= htmlspecialchars($user['username']) ?>'s Profile</h2>
        <?php if (!empty($user['profile_pic']) && file_exists("uploads/" . $user['profile_pic'])): ?>
            <img src="uploads/<?= htmlspecialchars($user['profile_pic']) ?>" alt="Profile Picture">
        <?php else: ?>
            <img src="uploads/default.jpg" alt="Default Profile Picture">
        <?php endif; ?>
        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
        <p><strong>Phone Number:</strong> <?= htmlspecialchars($user['mobile_number']) ?></p>
        <p><strong>Password:</strong> <?= htmlspecialchars($user['password']) ?></p>
    </div>
    <a href="dashboard.php">Go to Dashboard</a>

</body>
</html>
