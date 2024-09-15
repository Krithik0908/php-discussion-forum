<?php

session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'download' && isset($_POST['selected_posts'])) {
    $selected = array_map('intval', $_POST['selected_posts']);

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="selected_posts.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write CSV header row
    fputcsv($output, ['Q.ID', 'Title', 'Category', 'Question Author', 'Status', 'Answer', 'Answered By', 'Answered At']);

    // Prepare for dynamic IN clause in SQL query
    $placeholders = implode(',', array_fill(0, count($selected), '?'));
    $types = str_repeat('i', count($selected));

    // Select questions and their answers based on selected IDs
    $stmt = $conn->prepare("
        SELECT q.id, q.title, c.name AS category_name, q.username, q.status,
               a.body AS answer, a.username AS answered_by, a.created_at AS answered_at
        FROM questions q
        LEFT JOIN categories c ON q.category_id = c.id
        LEFT JOIN answers a ON a.question_id = q.id
        WHERE q.id IN ($placeholders)
        ORDER BY q.id, a.created_at
    ");
    $stmt->bind_param($types, ...$selected);
    $stmt->execute();
    $res = $stmt->get_result();

    // Loop through results and write each row to CSV
    while ($row = $res->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['title'],
            $row['category_name'] ?? 'Uncategorized',
            $row['username'],
            $row['status'],
            $row['answer'] ?? '',
            $row['answered_by'] ?? '',
            $row['answered_at'] ?? ''
        ]);
    }

    // Close the output stream
    fclose($output);
    exit; // Crucially, exit here to prevent further script execution and redirection
} else {
    // If the request method is not POST, or required parameters are missing, redirect
    header("Location: admin_posts.php");
    exit();
}
?>