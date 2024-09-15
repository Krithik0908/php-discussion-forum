<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$category_id = $_POST['category_id'];
$title = $_POST['title'];
$body = $_POST['body'];

$imagePath = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['image']['tmp_name'];
    $fileName = basename($_FILES['image']['name']);
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($fileExt, $allowedExts)) {
        $newFileName = uniqid() . '.' . $fileExt;
        $destPath = 'uploads/' . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $imagePath = $newFileName;
        } else {
            echo "Error uploading image.";
            exit;
        }
    } else {
        echo "Invalid image file type.";
        exit;
    }
}

// Ensure category is valid and not deleted
$catCheckStmt = $conn->prepare("SELECT COUNT(*) FROM categories WHERE id = ? AND is_deleted = FALSE");
$catCheckStmt->bind_param("i", $category_id);
$catCheckStmt->execute();
$catCheckStmt->bind_result($count);
$catCheckStmt->fetch();
$catCheckStmt->close();

if ($count == 0) {
    $_SESSION['error_message'] = "Cannot post question in a deleted or invalid category.";
    echo "Cannot post question in a deleted or invalid category.";
    exit;
}

// ✅ Set status to 'pending'
$stmt = $conn->prepare("INSERT INTO questions (username, category_id, title, body, image, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
$stmt->bind_param("sisss", $username, $category_id, $title, $body, $imagePath);
$stmt->execute();

// After successful question insert:
$stmt = $conn->prepare("UPDATE users SET points = points + 5 WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();


header("Location: dashboard.php");
exit;
?>
