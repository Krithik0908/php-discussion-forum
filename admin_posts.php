<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
include_once 'header.php';
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['post_id'])) {
    $postId = intval($_POST['post_id']);
    if ($_POST['action'] === 'toggle_status') {
        $stmt = $conn->prepare("SELECT status, username FROM questions WHERE id = ?");
        $stmt->bind_param("i", $postId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows === 1) {
            $post = $result->fetch_assoc();
            $oldStatus = $post['status'];
            $username = $post['username'];
            $newStatus = ($oldStatus === 'approved') ? 'blocked' : 'approved';

            $stmt = $conn->prepare("UPDATE questions SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $postId);
            if ($stmt->execute()) {
                if ($newStatus === 'approved' && $oldStatus !== 'approved') {
                    $stmt_points = $conn->prepare("UPDATE users SET points = points + 10 WHERE username = ?");
                    $stmt_points->bind_param("s", $username);
                    $stmt_points->execute();
                    $stmt_points->close();
                }
                $message = "Post status updated to " . ucfirst($newStatus) . ".";
            } else {
                $message = "Failed to update post status: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Post not found.";
            $message_type = 'error';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && $_POST['bulk_action'] !== '' && isset($_POST['selected_posts'])) {
    $action = $_POST['bulk_action'];
    $selected = array_map('intval', $_POST['selected_posts']);

    if (empty($selected)) {
        $message = "No posts selected for bulk action.";
        $message_type = 'warning';
    } else {
        $in = implode(',', array_fill(0, count($selected), '?'));
        $types = str_repeat('i', count($selected));

        if ($action === 'approve' || $action === 'block') {
            $newStatus = ($action === 'approve') ? 'approved' : 'blocked';
            $current_statuses = [];
            $stmt_fetch_status = $conn->prepare("SELECT id, username, status FROM questions WHERE id IN ($in)");
            $stmt_fetch_status->bind_param($types, ...$selected);
            $stmt_fetch_status->execute();
            $result_statuses = $stmt_fetch_status->get_result();
            while ($row = $result_statuses->fetch_assoc()) {
                $current_statuses[$row['id']] = ['status' => $row['status'], 'username' => $row['username']];
            }
            $stmt_fetch_status->close();

            $stmt_update = $conn->prepare("UPDATE questions SET status = ? WHERE id IN ($in)");
            $params_update = array_merge([$newStatus], $selected);
            $stmt_update->bind_param('s' . $types, ...$params_update);
            if ($stmt_update->execute()) {
                if ($action === 'approve') {
                    foreach ($selected as $id) {
                        if (isset($current_statuses[$id]) && $current_statuses[$id]['status'] !== 'approved') {
                            $uname = $current_statuses[$id]['username'];
                            $ptStmt = $conn->prepare("UPDATE users SET points = points + 10 WHERE username = ?");
                            $ptStmt->bind_param("s", $uname);
                            $ptStmt->execute();
                            $ptStmt->close();
                        }
                    }
                }
                $message = count($selected) . " posts bulk " . ucfirst($newStatus) . "d successfully.";
                $message_type = 'success';
            } else {
                $message = "Failed to apply bulk action: " . $stmt_update->error;
                $message_type = 'error';
            }
            $stmt_update->close();
        } elseif ($action === 'delete') {
            $stmt_delete = $conn->prepare("DELETE FROM questions WHERE id IN ($in)");
            $stmt_delete->bind_param($types, ...$selected);
            if ($stmt_delete->execute()) {
                $message = count($selected) . " posts bulk deleted successfully.";
                $message_type = 'success';
            } else {
                $message = "Failed to apply bulk delete action: " . $stmt_delete->error;
                $message_type = 'error';
            }
            $stmt_delete->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $allowedMimeTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        if ($file['size'] == 0) {
            $message = "Error: Uploaded file is empty.";
            $message_type = "error";
        } elseif (!in_array($file['type'], $allowedMimeTypes)) {
            $message = "Error: Invalid file type. Please upload a CSV, XLS, or XLSX file.";
            $message_type = "error";
        } else {
            $handle = fopen($file['tmp_name'], "r");
            if ($handle) {
                $header = fgetcsv($handle);
                $importedCount = 0;
                $skippedCount = 0;
                $errors = [];
                $columnMapping = [
                    'Title' => 'title',
                    'Content' => 'body',
                    'Author Username' => 'username',
                    'Category Name' => 'category_name'
                ];
                $headerIndexes = [];
                foreach ($columnMapping as $csvColumn => $dbColumn) {
                    $index = array_search($csvColumn, $header);
                    if ($index !== false) {
                        $headerIndexes[$dbColumn] = $index;
                    }
                }
                if (!isset($headerIndexes['title'], $headerIndexes['body'], $headerIndexes['username'], $headerIndexes['category_name'])) {
                    $message = "Error: Missing one or more required CSV headers (Title, Content, Author Username, Category Name).";
                    $message_type = "error";
                    fclose($handle);
                } else {
                    $stmt_insert = $conn->prepare("INSERT INTO questions (title, body, username, category_id, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
                    if ($stmt_insert === false) {
                        $message = "Database error preparing insert statement: " . $conn->error;
                        $message_type = "error";
                    } else {
                        while (($data = fgetcsv($handle)) !== FALSE) {
                            $csvRowNumber = $importedCount + $skippedCount + 2;
                            $title = trim($data[$headerIndexes['title']] ?? '');
                            $body = trim($data[$headerIndexes['body']] ?? '');
                            $authorUsername = trim($data[$headerIndexes['username']] ?? '');
                            $categoryName = trim($data[$headerIndexes['category_name']] ?? '');
                            if (empty($title) || empty($body) || empty($authorUsername) || empty($categoryName)) {
                                $skippedCount++;
                                $errors[] = "Row {$csvRowNumber}: Skipped due to missing data (Title, Content, Author, or Category).";
                                continue;
                            }
                            $userId = null;
                            $stmt_user = $conn->prepare("SELECT id FROM users WHERE username = ?");
                            $stmt_user->bind_param("s", $authorUsername);
                            $stmt_user->execute();
                            $res_user = $stmt_user->get_result();
                            if ($user_row = $res_user->fetch_assoc()) {
                            } else {
                                $skippedCount++;
                                $errors[] = "Row {$csvRowNumber}: Skipped. Author username '{$authorUsername}' does not exist.";
                                $stmt_user->close();
                                continue;
                            }
                            $stmt_user->close();
                            $categoryId = null;
                            $stmt_category = $conn->prepare("SELECT id FROM categories WHERE name = ?");
                            $stmt_category->bind_param("s", $categoryName);
                            $stmt_category->execute();
                            $res_category = $stmt_category->get_result();
                            if ($cat_row = $res_category->fetch_assoc()) {
                                $categoryId = $cat_row['id'];
                            } else {
                                $skippedCount++;
                                $errors[] = "Row {$csvRowNumber}: Skipped. Category '{$categoryName}' does not exist.";
                                $stmt_category->close();
                                continue;
                            }
                            $stmt_category->close();
                            $stmt_check_duplicate = $conn->prepare("SELECT id FROM questions WHERE title = ? AND username = ?");
                            $stmt_check_duplicate->bind_param("ss", $title, $authorUsername);
                            $stmt_check_duplicate->execute();
                            $stmt_check_duplicate->store_result();
                            if ($stmt_check_duplicate->num_rows > 0) {
                                $skippedCount++;
                                $errors[] = "Row {$csvRowNumber}: Skipped. Duplicate post found for title '{$title}' by '{$authorUsername}'.";
                                $stmt_check_duplicate->close();
                                continue;
                            }
                            $stmt_check_duplicate->close();
                            $stmt_insert->bind_param("sssi", $title, $body, $authorUsername, $categoryId);
                            if ($stmt_insert->execute()) {
                                $importedCount++;
                            } else {
                                $errors[] = "Row {$csvRowNumber}: Failed to import post '{$title}': " . $stmt_insert->error;
                            }
                        }
                        $stmt_insert->close();
                        if ($importedCount > 0) {
                            $message = "Successfully imported {$importedCount} posts.";
                            $message_type = 'success';
                        } else {
                            $message = "No new posts imported.";
                            $message_type = 'warning';
                        }
                        if ($skippedCount > 0) {
                            $message .= " {$skippedCount} posts skipped.";
                            $message_type = ($importedCount > 0) ? 'warning' : 'info';
                        }
                        if (!empty($errors)) {
                            $message .= "<br>Details:<br>" . implode("<br>", $errors);
                            $message_type = 'error';
                        }
                    }
                    fclose($handle);
                }
            } else {
                $message = "Error: Could not open the uploaded file.";
                $message_type = "error";
            }
        }
    } elseif ($file['error'] === UPLOAD_ERR_NO_FILE) {
        $message = "No file uploaded for import.";
        $message_type = "warning";
    } else {
        $message = "File upload error: " . $file['error'];
        $message_type = "error";
    }
}

$total_all = $conn->query("SELECT COUNT(*) AS total FROM questions")->fetch_assoc()['total'];
$total_approved = $conn->query("SELECT COUNT(*) AS total FROM questions WHERE status = 'approved'")->fetch_assoc()['total'];
$total_blocked = $conn->query("SELECT COUNT(*) AS total FROM questions WHERE status = 'blocked'")->fetch_assoc()['total'];
$sql = "
    SELECT q.id, q.title, q.username, q.status, c.name AS category_name
    FROM questions q
    LEFT JOIN categories c ON q.category_id = c.id";
$current_filter_status = $_GET['status'] ?? 'all';
if ($current_filter_status !== 'all') {
    $sql .= " WHERE q.status = ?";
}
$sql .= " ORDER BY q.id DESC";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    $message = "Database error preparing select statement: " . $conn->error;
    $message_type = 'error';
    $posts_result = null;
} else {
    if ($current_filter_status !== 'all') {
        $stmt->bind_param("s", $current_filter_status);
    }
    $stmt->execute();
    $posts_result = $stmt->get_result();
    $stmt->close();
}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<link rel="stylesheet" href="css/dropify.min.css">

<style>
:root {
    --primary: #6366f1;
    --secondary: #ec4899;
    --background: #f0f4ff;
    --text: #1e1e2f;
    --input-border: #d1d5db;
    --error-color: #e02424;
    --card-bg: #ffffff;
    --shadow-light: rgba(99, 102, 241, 0.15);
    --warning-color: #f59e0b;
    --info-color: #3b82f6;
}

body {
    font-family: 'Poppins', sans-serif;
    background: var(--background);
    color: var(--text);
    margin: 0;
}
.admin-panel {
    margin: 100px 0 20px 0;
    padding-left: 240px;
    padding-right: 20px;
    padding-top: 25px;
    padding-bottom: 25px;
    background: none;
    box-shadow: none;
    border-radius: 0;
    width: 100%;
    box-sizing: border-box;
}

h2 {
    margin-bottom: 28px;
    text-align: center;
    color: var(--primary);
    font-weight: 700;
    font-size: 28px;
}

.message {
    margin-bottom: 20px;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    font-weight: 600;
    font-size: 16px;
    color: green;
    background-color: #e6ffe6;
    border: 1px solid #c6ffc6;
}
.message.error {
    color: var(--error-color);
    background-color: #ffe6e6;
    border-color: #ffc6c6;
}
.message.warning {
    color: var(--warning-color);
    background-color: #fffbe6;
    border-color: #ffeb99;
}
.message.info {
    color: var(--info-color);
    background-color: #e6f7ff;
    border-color: #b3e0ff;
}

table {
    border-collapse: collapse;
    width: 100%;
    margin-bottom: 25px;
    background: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

th, td {
    border: 1px solid var(--input-border);
    padding: 12px 8px;
    text-align: left;
}

th {
    background-color: var(--primary);
    color: white;
    font-weight: 600;
}

td {
    background-color: var(--card-bg);
}

tr:nth-child(even) td {
    background-color: #f9f9f9;
}

button {
    padding: 8px 15px;
    background-color: var(--primary);
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

button:hover {
    background-color: #4b47c9;
}

.bulk-actions-container {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.bulk-actions-container select {
    padding: 8px 10px;
    border: 1px solid var(--input-border);
    border-radius: 6px;
    background-color: var(--card-bg);
    font-size: 14px;
}

.bulk-actions-container button {
    background-color: var(--secondary);
    padding: 8px 15px;
}

.bulk-actions-container button:hover {
    background-color: #d03b87;
}

.tabs {
    margin-bottom: 20px;
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}

.tabs a {
    padding: 10px 20px;
    background-color: #e0e0e0;
    color: #333;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 600;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.tabs a.active {
    background-color: var(--primary);
    color: white;
}

.approved {
}

.blocked {
}

.dataTables_filter {
    float: right;
    margin-bottom: 10px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 6px 12px;
    border: 1px solid var(--input-border);
    border-radius: 6px;
    background-color: var(--card-bg);
    color: var(--text) !important;
    margin: 0 2px;
    cursor: pointer;
    transition: background-color 0.2s ease, border-color 0.2s ease;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background-color: var(--primary);
    color: white !important;
    border-color: var(--primary);
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current,
.dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    background-color: var(--primary);
    color: white !important;
    border-color: var(--primary);
}

.dataTables_wrapper .dataTables_info {
    padding-top: 8px;
    color: #555;
    font-size: 14px;
}
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
    margin-bottom: 15px;
}

.bottom-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
    flex-wrap: wrap;
    gap: 10px;
}
.bulk-actions-area {
    display: flex;
    align-items: center;
    gap: 10px;
}
.pagination-info-area {
    display: flex;
    align-items: center;
    gap: 10px;
}


.modal {
    display: none;
    position: fixed;
    z-index: 10001;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
    backdrop-filter: blur(3px);
}

.modal-content {
    background-color: #fefefe;
    margin: 10% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 700px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    position: relative;
    animation: fadeIn 0.3s ease-out;
}

.close-button {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close-button:hover,
.close-button:focus {
    color: #000;
    text-decoration: none;
    cursor: pointer;
}

.answer-item {
    background-color: var(--background);
    border: 1px solid var(--input-border);
    border-radius: 5px;
    padding: 10px;
    margin-bottom: 10px;
}

.answer-header {
    font-weight: bold;
    color: var(--primary);
    margin-bottom: 5px;
}

.answer-body {
    margin-bottom: 5px;
}

.reply-item {
    margin-left: 20px;
    border-left: 3px solid var(--secondary);
    padding-left: 10px;
    background-color: #f8f8ff;
    border-radius: 3px;
    margin-top: 5px;
}

.no-answers-message {
    text-align: center;
    padding: 20px;
    font-style: italic;
    color: #555;
}

.import-section {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    justify-content: center;
    flex-wrap: wrap;
    background-color: var(--card-bg);
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.import-section h3 {
    margin: 0;
    color: var(--primary);
    font-size: 1.2em;
}
.import-section input[type="file"] {
    border: 2px solid var(--input-border);
    padding: 8px;
    border-radius: 6px;
    background-color: #fafafa;
    color: var(--text);
    font-size: 14px;
    cursor: pointer;
}
.import-section button {
    background: linear-gradient(90deg, #28a745, #218838);
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
    min-width: 120px;
}
.import-section button:hover {
    background: linear-gradient(90deg, #218838, #1e7e34);
    box-shadow: 0 6px 12px rgba(40, 167, 69, 0.3);
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    .admin-panel {
        padding-left: 20px;
    }
    .bulk-actions-container, .import-section {
        flex-direction: column;
        align-items: center;
    }
    .import-section input[type="file"] {
        width: 80%;
        max-width: 300px;
    }
    .bottom-section {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="main">
    <h2>Admin: Manage Posts</h2>

    <?php if ($message): ?>
        <div class="message <?= $message_type ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="tabs">
        <a href="admin_posts.php?status=all" class="<?= ($current_filter_status === 'all') ? 'active' : '' ?>">All (<?= $total_all ?>)</a>
        <a href="admin_posts.php?status=approved" class="<?= ($current_filter_status === 'approved') ? 'active' : '' ?>">Active (<?= $total_approved ?>)</a>
        <a href="admin_posts.php?status=blocked" class="<?= ($current_filter_status === 'blocked') ? 'active' : '' ?>">Inactive (<?= $total_blocked ?>)</a>
    </div>

    <div class="import-section">
        <h3>Import Posts:</h3>
        <form action="" method="POST" enctype="multipart/form-data">
           <input type="file" name="csv_file" class="dropify" data-allowed-file-extensions="csv xls xlsx" data-max-file-size="5M" accept=".csv, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            <button type="submit">Import</button>
        </form>
    </div>

<?php if ($posts_result && $posts_result->num_rows > 0): ?>
    <form method="POST" id="bulk-action-form" action="admin_posts.php">
        <table id="posts-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Author</th>
                    <th>Status</th>
                    <th>Answers</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($post = $posts_result->fetch_assoc()): ?>
                    <tr class="<?= $post['status'] === 'approved' ? 'approved' : 'blocked' ?>">
                        <td><input type="checkbox" name="selected_posts[]" value="<?= $post['id'] ?>"></td>
                        <td><?= htmlspecialchars($post['title']) ?></td>
                        <td><?= htmlspecialchars($post['category_name'] ?? 'Uncategorized') ?></td>
                        <td><?= htmlspecialchars($post['username']) ?></td>
                        <td><?= ucfirst($post['status']) ?></td>
                        <td>
                            <button type="button" class="view-answers-btn" data-post-id="<?= $post['id'] ?>">View</button>
                        </td>
                        <td>
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <button type="submit"><?= $post['status'] === 'approved' ? 'Block' : 'Approve' ?></button>
                            </form>
                            <form method="GET" action="edit_post.php" style="display:inline-block; margin-left: 5px;">
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <button type="submit">Edit</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div id="original-bulk-actions-container" style="display:none;">
            <div class="bulk-actions-container">
                <label for="bulk_action_select">With selected:</label>
                <select name="bulk_action" id="bulk_action_select">
                    <option value="">Select Action</option>
                    <option value="approve">Approve Selected</option>
                    <option value="block">Block Selected</option>
                    <option value="delete">Delete Selected</option>
                    <option value="download">Download Selected</option>
                </select>
                <button type="submit" id="apply-bulk-action-btn">Apply</button>
            </div>
        </div>
    </form>


    <?php else: ?>
        <p>No posts found.</p>
    <?php endif; ?>
</div>

<div id="answersModal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h3>Answers for Post: <span id="modalPostTitle"></span></h3>
        <div id="answersContent">
            <p>Loading answers...</p>
        </div>
    </div>
</div>

<script src="js/dropify.min.js"></script>

<script>
    $(document).ready(function(){
        $('.dropify').dropify();
    });
    $(document).ready(function () {
        var dataTable = $('#posts-table').DataTable({
            paging: true,
            ordering: true,
            searching: true,
            pageLength: 5,
            lengthMenu: [5, 10, 20, 50],
            dom: '<"top"lf<"clear">>t<"bottom-section" <"bulk-actions-area"> <"pagination-info-area"ip>>'
        });

        var urlParams = new URLSearchParams(window.location.search);
        var filterStatus = urlParams.get('status');
        if (filterStatus && filterStatus !== 'all') {
            dataTable.column(4).search('^' + filterStatus + '$', true, false).draw();
        } else {
            dataTable.column(4).search('').draw();
        }

        $('.bulk-actions-area').append($('#original-bulk-actions-container').html());
        $('#original-bulk-actions-container').remove();


        $('#select-all').on('click', function () {
            var checkboxes = dataTable.rows({ page: 'current' }).nodes().to$().find('input[type="checkbox"][name="selected_posts[]"]');
            checkboxes.prop('checked', this.checked);
        });

        $('#apply-bulk-action-btn').on('click', function(e) {
            e.preventDefault();

            var selectedAction = $('#bulk_action_select').val();
            var selectedPostIds = [];
            $('input[name="selected_posts[]"]:checked').each(function() {
                selectedPostIds.push($(this).val());
            });

            if (selectedAction === '') {
                alert('Please select a bulk action.');
                return;
            }

            if (selectedPostIds.length === 0) {
                alert('Please select at least one post to apply the action.');
                return;
            }

            if (selectedAction === 'delete') {
                if (!confirm('Are you sure you want to delete the selected posts? This action cannot be undone.')) {
                    return;
                }
            }


            if (selectedAction === 'download') {
                var downloadForm = $('<form>', {
                    action: 'download_posts.php',
                    method: 'POST',
                    target: '_blank'
                });
                $.each(selectedPostIds, function(index, id) {
                    downloadForm.append($('<input>', {
                        type: 'hidden',
                        name: 'selected_posts[]',
                        value: id
                    }));
                });
                downloadForm.append($('<input>', {
                    type: 'hidden',
                    name: 'bulk_action',
                    value: 'download'
                }));
                $('body').append(downloadForm);
                downloadForm.submit();
                downloadForm.remove();
            } else {
                $('#bulk-action-form input[name="selected_posts[]"]').remove();

                $.each(selectedPostIds, function(index, id) {
                    $('#bulk-action-form').append($('<input>', {
                        type: 'hidden',
                        name: 'selected_posts[]',
                        value: id
                    }));
                });
                $('#bulk-action-form').submit();
            }
        });

        dataTable.on('draw.dt', function() {
            var totalVisible = dataTable.rows({ page: 'current' }).nodes().to$().find('input[type="checkbox"][name="selected_posts[]"]').length;
            var checkedVisible = dataTable.rows({ page: 'current' }).nodes().to$().find('input[type="checkbox"][name="selected_posts[]"]:checked').length;
            $('#select-all').prop('checked', totalVisible > 0 && totalVisible === checkedVisible);
        });

        $('#posts-table tbody').on('change', 'input[type="checkbox"][name="selected_posts[]"]', function() {
            var totalVisible = dataTable.rows({ page: 'current' }).nodes().to$().find('input[type="checkbox"][name="selected_posts[]"]').length;
            var checkedVisible = dataTable.rows({ page: 'current' }).nodes().to$().find('input[type="checkbox"][name="selected_posts[]"]:checked').length;
            $('#select-all').prop('checked', totalVisible > 0 && totalVisible === checkedVisible);
        });

        $(document).on('click', '.view-answers-btn', function() {
            var postId = $(this).data('post-id');
            var postTitle = $(this).closest('tr').find('td:eq(1)').text();

            $('#modalPostTitle').text(postTitle);
            $('#answersContent').html('<p>Loading answers...</p>');
            $('#answersModal').css('display', 'block');

            $.ajax({
                url: 'fetch_answers.php',
                type: 'GET',
                data: { post_id: postId },
                dataType: 'json',
                success: function(data) {
                    var answersHtml = '';
                    if (data.error) {
                        answersHtml = '<p class=\"no-answers-message\">' + data.error + '</p>';
                    } else if (data.length === 0) {
                        answersHtml = '<p class=\"no-answers-message\">No answers yet.</p>';
                    } else {
                        $.each(data, function(index, answer) {
                            answersHtml += '<div class=\"answer-item\">';
                            answersHtml += '<div class=\"answer-header\">Answer by ' + htmlspecialchars(answer.username) + ' on ' + answer.created_at + '</div>';
                            answersHtml += '<div class=\"answer-body\">' + nl2br(htmlspecialchars(answer.body)) + '</div>';
                            if (answer.replies && answer.replies.length > 0) {
                                $.each(answer.replies, function(rIndex, reply) {
                                    answersHtml += '<div class=\"reply-item\">';
                                    answersHtml += '<strong>Reply by ' + htmlspecialchars(reply.username) + '</strong> on ' + reply.created_at + ':<br>';
                                    answersHtml += nl2br(htmlspecialchars(reply.body));
                                    answersHtml += '</div>';
                                });
                            }
                            answersHtml += '</div>';
                        });
                    }
                    $('#answersContent').html(answersHtml);
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error, xhr.responseText);
                    $('#answersContent').html('<p class=\"no-answers-message\">Error loading answers. Please try again.</p>');
                }
            });
        });

        $('.close-button').on('click', function() {
            $('#answersModal').css('display', 'none');
        });

        $(window).on('click', function(event) {
            if ($(event.target).is('#answersModal')) {
                $('#answersModal').css('display', 'none');
            }
        });

        function htmlspecialchars(str) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '\"': '&quot;',
                "\'": '&#039;'
            };
            return str.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function nl2br(str) {
            return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
        }
    });
</script>

<?php include_once 'footer.php'; ?>