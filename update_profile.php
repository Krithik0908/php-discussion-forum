<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$email = $_POST['email'] ?? '';
$mobile_number = $_POST['mobile_number'] ?? '';
$password = $_POST['password'] ?? '';
$profile_pic = '';


if (!empty($_FILES["profile_pic"]['name'])) {
    $target_dir = "uploads/";

    $original_name = basename($_FILES["profile_pic"]["name"]);
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $sanitized_name = preg_replace("/[^a-zA-Z0-9_\-]/", "_", pathinfo($original_name, PATHINFO_FILENAME));
    $profile_pic = $sanitized_name . "_" . time() . "." . $extension;
    $target_file = $target_dir . $profile_pic;

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
      
        $stmt = $conn->prepare("UPDATE users SET password = ?, mobile_number = ?, email = ?, profile_pic = ? WHERE username = ?");
        $stmt->bind_param("sssss",$password, $mobile_number, $email, $profile_pic, $username);
    } else {
        echo "Error uploading the file.";
        exit;
    }
} else {
  
    $stmt = $conn->prepare("UPDATE users SET password = ?, mobile_number = ?, email = ? WHERE username = ?");
    $stmt->bind_param("ssss",$password, $mobile_number, $email, $username);
}

$stmt->execute();
$stmt->close();
header("Location: profile.php");
exit;
?>
