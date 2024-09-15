<?php
session_start();
require 'db.php'; 

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

$stmt = $conn->prepare("SELECT * FROM questions WHERE username = ? ORDER BY created_at DESC");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Questions</title>
</head>
<body>
    <h2>My Questions</h2>
    <ul>
        <?php while ($row = $result->fetch_assoc()): ?>
            <li>
                <a href="view_question.php?id=<?= $row['id'] ?>">
                    <?= htmlspecialchars($row['title']) ?>
                </a> 
                (<?= $row['created_at'] ?>)
            </li>
        <?php endwhile; ?>
    </ul>
    
</body>
</html>
