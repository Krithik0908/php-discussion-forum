<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

$stmt = $conn->prepare("
    SELECT r.*, q.title, a.body AS answer_body
    FROM replies r
    JOIN answers a ON r.answer_id = a.id
    JOIN questions q ON a.question_id = q.id
    WHERE r.username = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Replies</title>
</head>
<body>
    <h2>My Replies</h2>
    <ul>
        <?php while ($row = $result->fetch_assoc()): ?>
            <li>
                On question: 
                <a href="view_question.php?id=<?= $row['question_id'] ?>">
                    <?= htmlspecialchars($row['title']) ?>
                </a><br>
                <em>Reply to:</em> <?= nl2br(htmlspecialchars($row['answer_body'])) ?><br>
                <strong>My reply:</strong> <?= nl2br(htmlspecialchars($row['body'])) ?>
                (<?= $row['created_at'] ?>)
            </li>
        <?php endwhile; ?>
    </ul>
      <a href="dashboard.php">← Back to Dashboard</a>
</body>
</html>
