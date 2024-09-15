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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $userId = intval($_POST['user_id']);

        if ($_POST['action'] === 'toggle_status') {
            $stmt = $conn->prepare("SELECT is_active FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $row = $result->fetch_assoc();
                $newStatus = $row['is_active'] ? 0 : 1;
                $stmt_update = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                $stmt_update->bind_param("ii", $newStatus, $userId);
                if ($stmt_update->execute()) {
                    $message = "User status updated.";
                    $message_type = 'success';
                } else {
                    $message = "Failed to update user status: " . $stmt_update->error;
                    $message_type = 'error';
                }
                $stmt_update->close();
            } else {
                $message = "User not found.";
                $message_type = 'error';
            }
            $stmt->close();
        }

        if ($_POST['action'] === 'update_role' && isset($_POST['new_role'])) {
            $newRole = $_POST['new_role'];
            $validRoles = ['user', 'operator', 'admin'];
            if (in_array($newRole, $validRoles)) {
                $stmt_update = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt_update->bind_param("si", $newRole, $userId);
                if ($stmt_update->execute()) {
                    $message = "User role updated.";
                    $message_type = 'success';
                } else {
                    $message = "Failed to update user role: " . $stmt_update->error;
                    $message_type = 'error';
                }
                $stmt_update->close();
            } else {
                $message = "Invalid role selected.";
                $message_type = 'error';
            }
        }
    }

    if (isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file'];
        $allowedMimeTypes = ['text/csv', 'application/vnd.ms-excel', 'application/csv'];

        if ($file['error'] === UPLOAD_ERR_OK) {
            if ($file['size'] == 0) {
                $message = "Error: Uploaded file is empty.";
                $message_type = "error";
            } elseif (!in_array($file['type'], $allowedMimeTypes)) {
                $message = "Error: Invalid file type. Please upload a CSV file.";
                $message_type = "error";
            } else {
                $handle = fopen($file['tmp_name'], "r");
                if ($handle) {
                    $header = fgetcsv($handle);

                    $importedCount = 0;
                    $updatedCount = 0;
                    $skippedCount = 0;
                    $errors = [];

                    $columnMapping = [
                        'Username' => 'username',
                        'Email' => 'email',
                        'Password' => 'password',
                        'Mobile Number' => 'mobile_number',
                        'Role' => 'role',
                        'Is Active' => 'is_active'
                    ];

                    $headerIndexes = [];
                    foreach ($columnMapping as $csvColumn => $dbColumn) {
                        $index = array_search($csvColumn, $header);
                        if ($index !== false) {
                            $headerIndexes[$dbColumn] = $index;
                        }
                    }

                    $requiredHeadersExist = true;
                    $missingHeaders = [];
                    foreach (['username', 'email', 'password'] as $required) {
                        if (!isset($headerIndexes[$required])) {
                            $requiredHeadersExist = false;
                            $missingHeaders[] = array_search($required, $columnMapping);
                        }
                    }

                    if (!$requiredHeadersExist) {
                        $message = "Error: Missing one or more required CSV headers for users (" . implode(', ', $missingHeaders) . ").";
                        $message_type = "error";
                        fclose($handle);
                    } else {
                        $stmt_check_user = $conn->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
                        $stmt_insert_user = $conn->prepare("INSERT INTO users (username, email, password, mobile_number, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        $stmt_update_user = $conn->prepare("UPDATE users SET email = ?, password = ?, mobile_number = ?, role = ?, is_active = ? WHERE username = ?");

                        if ($stmt_check_user === false || $stmt_insert_user === false || $stmt_update_user === false) {
                            $message = "Database error preparing statements: " . $conn->error;
                            $message_type = "error";
                        } else {
                            while (($data = fgetcsv($handle)) !== FALSE) {
                                $csvRowNumber = $importedCount + $updatedCount + $skippedCount + 2;

                                $username = trim($data[$headerIndexes['username']] ?? '');
                                $email = trim($data[$headerIndexes['email']] ?? '');
                                $password = trim($data[$headerIndexes['password']] ?? '');
                                $mobileNumber = trim($data[$headerIndexes['mobile_number']] ?? '');
                                $role = strtolower(trim($data[$headerIndexes['role']] ?? 'user'));
                                $isActive = trim($data[$headerIndexes['is_active']] ?? '1');

                                if (empty($username) || empty($email) || empty($password)) {
                                    $skippedCount++;
                                    $errors[] = "Row {$csvRowNumber}: Skipped due to missing required data (Username, Email, or Password).";
                                    continue;
                                }

                                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    $skippedCount++;
                                    $errors[] = "Row {$csvRowNumber}: Skipped. Invalid email format for '{$email}'.";
                                    continue;
                                }

                                $validRoles = ['user', 'operator', 'admin'];
                                if (!in_array($role, $validRoles)) {
                                    $errors[] = "Row {$csvRowNumber}: Invalid role '{$role}' for user '{$username}'. Defaulting to 'user'.";
                                    $role = 'user';
                                }

                                $isActive = (in_array(strtolower($isActive), ['1', 'true', 'active'])) ? 1 : 0;


                                $stmt_check_user->bind_param("ss", $username, $email);
                                $stmt_check_user->execute();
                                $check_result = $stmt_check_user->get_result();
                                $existing_user = $check_result->fetch_assoc();

                                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                                if ($existing_user) {
                                    if ($existing_user['username'] !== $username && $existing_user['email'] === $email) {
                                        $skippedCount++;
                                        $errors[] = "Row {$csvRowNumber}: Skipped. Email '{$email}' already belongs to another user ('{$existing_user['username']}').";
                                        continue;
                                    }

                                    $stmt_update_user->bind_param("ssssis", $email, $hashedPassword, $mobileNumber, $role, $isActive, $username);
                                    if ($stmt_update_user->execute()) {
                                        $updatedCount++;
                                    } else {
                                        $errors[] = "Row {$csvRowNumber}: Failed to update user '{$username}': " . $stmt_update_user->error;
                                    }
                                } else {
                                    $stmt_insert_user->bind_param("sssssi", $username, $email, $hashedPassword, $mobileNumber, $role, $isActive);
                                    if ($stmt_insert_user->execute()) {
                                        $importedCount++;
                                    } else {
                                        if ($conn->errno == 1062 && strpos($conn->error, 'for key \'email\'') !== false) {
                                            $skippedCount++;
                                            $errors[] = "Row {$csvRowNumber}: Skipped. Email '{$email}' already exists.";
                                        } else {
                                            $errors[] = "Row {$csvRowNumber}: Failed to import user '{$username}': " . $stmt_insert_user->error;
                                        }
                                    }
                                }
                            }
                            $stmt_check_user->close();
                            $stmt_insert_user->close();
                            $stmt_update_user->close();
                        }
                        fclose($handle);

                        $final_message = "Import process completed: ";
                        if ($importedCount > 0) $final_message .= "{$importedCount} new users imported. ";
                        if ($updatedCount > 0) $final_message .= "{$updatedCount} users updated. ";
                        if ($skippedCount > 0) $final_message .= "{$skippedCount} rows skipped. ";

                        if ($importedCount > 0 || $updatedCount > 0) {
                            $message_type = 'success';
                        } else if ($skippedCount > 0 && empty($errors)) {
                            $message_type = 'warning';
                        } else {
                            $message_type = 'error';
                        }

                        $message = $final_message;
                        if (!empty($errors)) {
                            $message .= "<br>Detailed issues:<br>" . implode("<br>", $errors);
                        }
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
}

$sql = "SELECT id, username, email, mobile_number, is_active, role FROM users WHERE username != ?";
$params = [$_SESSION['username']];
$types = "s";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    $message = "Database error: " . $conn->error;
    $users = null;
    $message_type = 'error';
} else {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $users = $stmt->get_result();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Users Management</title>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

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
            font-family: 'Arial', sans-serif;
            padding: 40px;
            background-color: var(--background);
            color: var(--text);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        h2 {
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }
        .message {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            font-size: 16px;
        }
        .message.success {
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

        table.dataTable {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            background-color: white;
            width: 100% !important;
        }
        table.dataTable thead {
            background-color: #6366f1;
            color: white;
        }
        table.dataTable th, table.dataTable td {
            padding: 12px 10px;
            font-size: 14px;
            border-bottom: 1px solid #ccc;
            border-right: 1px solid #ccc;
        }
        table.dataTable th:last-child, table.dataTable td:last-child {
            border-right: none;
        }
        table.dataTable tbody tr:last-child td {
            border-bottom: none;
        }

        .blocked {
            background-color: #ffe6e6 !important;
            color: #5a5a5a;
        }
        select, button {
            padding: 6px 10px;
            border-radius: 5px;
            font-size: 14px;
            border: 1px solid #ccc;
            background-color: #f1f1f1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        button {
            background-color: #6366f1;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #4b47c9;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 6px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        .dt-buttons {
            margin-bottom: 15px;
        }
        .dt-buttons button {
            margin-right: 5px;
            background-color: #4b47c9;
            color: white;
            border-radius: 5px;
            border: none;
            padding: 6px 12px;
            font-size: 13px;
        }
        .dt-buttons button:hover {
            background-color: #3a35a0;
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
            border: 1px solid #e0e0e0;
        }
        .import-section h3 {
            margin: 0;
            color: var(--primary);
            font-size: 1.2em;
            white-space: nowrap;
        }
        .import-section input[type="file"] {
            border: 2px solid var(--input-border);
            padding: 8px;
            border-radius: 6px;
            background-color: #fafafa;
            color: var(--text);
            font-size: 14px;
            cursor: pointer;
            flex-grow: 1;
            max-width: 350px;
        }
        .import-section button {
            background: linear-gradient(90deg, #28a745, #218838);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
            min-width: 120px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .import-section button:hover {
            background: linear-gradient(90deg, #218838, #1e7e34);
            box-shadow: 0 6px 12px rgba(40, 167, 69, 0.3);
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            body {
                padding: 20px;
            }
            .container {
                padding: 15px;
            }
            .import-section {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            .import-section h3, .import-section button {
                width: 100%;
                text-align: center;
            }
            .import-section input[type="file"] {
                width: 100%;
                max-width: none;
            }
            table.dataTable th, table.dataTable td {
                padding: 8px 6px;
                font-size: 12px;
            }
            .dt-buttons button {
                margin-bottom: 5px;
                width: 100%;
            }
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/vfs_fonts.js"></script>
</head>
<body>
    <div class="container">
        <h2>Admin: Manage Users</h2>

        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="import-section">
            <h3>Import Users:</h3>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="file" name="csv_file" class="dropify" data-allowed-file-extensions="csv xls xlsx" data-max-file-size="5M" accept=".csv, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                <button type="submit">Import</button>
            </form>
        </div>

        <?php if ($users && $users->num_rows > 0): ?>
            <table id="usersTable" class="display nowrap">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Status</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                    <tr class="<?= $user['is_active'] ? '' : 'blocked' ?>">
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['mobile_number']) ?></td>
                        <td><?= $user['is_active'] ? 'Active' : 'Blocked' ?></td>
                        <td>
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <input type="hidden" name="action" value="update_role">
                                <select name="new_role" onchange="this.form.submit()">
                                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                    <option value="operator" <?= $user['role'] === 'operator' ? 'selected' : '' ?>>Operator</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </form>
                        </td>
                        <td>
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <button type="submit"><?= $user['is_active'] ? 'Block' : 'Unblock' ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No users found.</p>
        <?php endif; ?>
    </div>
    <script src="js/dropify.min.js"></script>
    <script>
        $(document).ready(function(){
           $('.dropify').dropify();
        });
        $(document).ready(function() {
            $('#usersTable').DataTable({
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50, 100],
                order: [[0, 'asc']],
                responsive: true,
                dom: 'Bfrtip',
                buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
            });
        });
    </script>
</body>
</html>
<?php include_once 'footer.php'; ?>