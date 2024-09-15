<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

$stmt = $conn->prepare("
    SELECT a.*, q.title 
    FROM answers a 
    JOIN questions q ON a.question_id = q.id 
    WHERE a.username = ? 
    ORDER BY a.created_at DESC
");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Answers</title>
</head>
<body>
    <h2>My Answers</h2>
    <ul>
        <?php while ($row = $result->fetch_assoc()): ?>
            <li>
                <a href="view_question.php?id=<?= $row['question_id'] ?>">
                    <?= htmlspecialchars($row['title']) ?>
                </a>:
                <?= nl2br(htmlspecialchars($row['body'])) ?> 
                (<?= $row['created_at'] ?>)
            </li>
        <?php endwhile; ?>
    </ul>
      <a href="dashboard.php">← Back to Dashboard</a>
</body>
</html>
