<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'], $_POST['answer_id'])) {
    $body = trim($_POST['reply']);
    $answer_id = intval($_POST['answer_id']);
    $username = $_SESSION['username'];
    $status = 'approved';
    $createdAt = date('Y-m-d H:i:s');

$stmt = $conn->prepare("INSERT INTO replies (answer_id, username, body, status, created_at) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("issss", $answer_id, $username, $body, $status, $createdAt);

// After successful question insert:
$stmt = $conn->prepare("UPDATE users SET points = points + 5 WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();


}

header('Location: dashboard.php');
exit();
