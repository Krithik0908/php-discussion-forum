<?php
$host = 'localhost';
$user = 'root';
$password = 'root@123';
$database = 'qna';

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>