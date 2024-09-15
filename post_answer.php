<?php
session_start();
include 'db.php';

if (isset($_SESSION['username']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $answer = $_POST['body']; 
    $question_id = $_POST['question_id'];
    $user = $_SESSION['username'];

    $image_path = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image_name = basename($_FILES['image']['name']);
        $target_dir = "uploads/";
        $target_file = $target_dir . time() . "_" . $image_name;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
        }
    }

    $stmt = $conn->prepare("INSERT INTO answers (question_id, body, username, image) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $question_id, $answer, $user, $image_path);
    $stmt->execute();

$stmt = $conn->prepare("UPDATE users SET points = points + 5 WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();



    header("Location: dashboard.php");
    exit;
}
?>
