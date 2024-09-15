<?php

session_start();
include 'db.php';

header("Content-Type: application/json");

if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

if (isset($_GET['post_id']) && is_numeric($_GET['post_id'])) {
    $postId = intval($_GET['post_id']);

    $stmt = $conn->prepare("
        SELECT a.id, a.body, a.username, a.created_at,
               r.id AS reply_id, r.body AS reply_body, r.username AS reply_username, r.created_at AS reply_created_at
        FROM answers a
        LEFT JOIN replies r ON a.id = r.answer_id
        WHERE a.question_id = ?
        ORDER BY a.created_at ASC, r.created_at ASC
    ");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $result = $stmt->get_result();

    $groupedAnswers = [];

    while ($row = $result->fetch_assoc()) {
        $answerId = $row['id'];
        if (!isset($groupedAnswers[$answerId])) {
            $groupedAnswers[$answerId] = [
                'id' => $row['id'],
                'body' => $row['body'],
                'username' => $row['username'],
                'created_at' => $row['created_at'],
                'replies' => []
            ];
        }
        if ($row['reply_id']) {
            $groupedAnswers[$answerId]['replies'][] = [
                'id' => $row['reply_id'],
                'body' => $row['reply_body'],
                'username' => $row['reply_username'],
                'created_at' => $row['reply_created_at']
            ];
        }
    }
    $answers = array_values($groupedAnswers);

    echo json_encode($answers);

    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid post ID.']);
}
$conn->close();
?>