<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_category' && isset($_POST['category_id'])) {
        $categoryId = intval($_POST['category_id']);
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $categoryId);
        if ($stmt->execute()) {
            $message = "Category deleted successfully.";
        } else {
            $message = "Failed to delete category: " . $stmt->error;
        }
        $stmt->close();
    }
    // Bulk delete logic (already present, no change needed)
    if ($_POST['action'] === 'bulk_delete' && isset($_POST['selected_ids'])) {
        $ids = array_map('intval', $_POST['selected_ids']);
        if (!empty($ids)) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $stmt = $conn->prepare("DELETE FROM categories WHERE id IN ($in)");
            $stmt->bind_param($types, ...$ids);
            if ($stmt->execute()) {
                $message = count($ids) . " categories deleted successfully.";
            } else {
                $message = "Failed to delete selected categories: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "No categories selected for bulk deletion.";
        }
    }
}

// Handle file upload for import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $allowedMimeTypes = ['text/csv', 'application/vnd.ms-excel']; // Common CSV MIME types

    if ($file['error'] === UPLOAD_ERR_OK) {
        // Basic file validation
        if ($file['size'] == 0) {
            $message = "Error: Uploaded file is empty.";
            $message_type = "error";
        } elseif (!in_array($file['type'], $allowedMimeTypes)) {
            $message = "Error: Invalid file type. Please upload a CSV file.";
            $message_type = "error";
        } else {
            $handle = fopen($file['tmp_name'], "r");
            if ($handle) {
                $header = fgetcsv($handle); // Read the header row

                $importedCount = 0;
                $skippedCount = 0;
                $errors = [];

                // Assuming CSV format: Category Name
                $expectedHeader = ['Category Name']; // Or just 'Name', depending on your CSV

                // Optional: Validate header
                if (count($header) < 1 || !in_array('Category Name', $header) && !in_array('Name', $header)) {
                    $message = "Error: CSV header is missing 'Category Name' or 'Name' column.";
                    $message_type = "error";
                    fclose($handle);
                } else {
                    $nameColumnIndex = array_search('Category Name', $header);
                    if ($nameColumnIndex === false) {
                        $nameColumnIndex = array_search('Name', $header);
                    }

                    $stmt_insert = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
                    if ($stmt_insert === false) {
                        $message = "Database error preparing insert statement: " . $conn->error;
                        $message_type = "error";
                    } else {
                        while (($data = fgetcsv($handle)) !== FALSE) {
                            if (isset($data[$nameColumnIndex]) && trim($data[$nameColumnIndex]) !== '') {
                                $categoryName = trim($data[$nameColumnIndex]);

                                // Check if category already exists (optional, but good for preventing duplicates)
                                $stmt_check = $conn->prepare("SELECT id FROM categories WHERE name = ?");
                                $stmt_check->bind_param("s", $categoryName);
                                $stmt_check->execute();
                                $stmt_check->store_result();

                                if ($stmt_check->num_rows > 0) {
                                    $skippedCount++;
                                    // You can log or add to errors if you want to be more specific about skipped items
                                    // $errors[] = "Category '{$categoryName}' already exists.";
                                } else {
                                    $stmt_insert->bind_param("s", $categoryName);
                                    if ($stmt_insert->execute()) {
                                        $importedCount++;
                                    } else {
                                        $errors[] = "Failed to import '{$categoryName}': " . $stmt_insert->error;
                                    }
                                }
                                $stmt_check->close();
                            } else {
                                $skippedCount++;
                                $errors[] = "Skipped row due to empty category name: " . implode(',', $data);
                            }
                        }
                        $stmt_insert->close();

                        if ($importedCount > 0) {
                            $message = "Successfully imported {$importedCount} categories.";
                            if ($skippedCount > 0) {
                                $message .= " {$skippedCount} categories skipped (already exist or empty name).";
                            }
                            $message_type = "success";
                        } else if ($skippedCount > 0) {
                             $message = "No new categories imported. {$skippedCount} categories skipped (already exist or empty name).";
                             $message_type = "info"; // Or success if you consider skipped as success in preventing duplicates
                        }
                        else {
                            $message = "No categories found in the CSV file or no new categories to import.";
                            $message_type = "warning";
                        }

                        if (!empty($errors)) {
                            $message .= "<br>Errors: " . implode("<br>", $errors);
                            $message_type = "error"; // Elevate to error if there were actual errors
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
        $message = "No file uploaded.";
        $message_type = "warning";
    } else {
        $message = "File upload error: " . $file['error'];
        $message_type = "error";
    }
}

$sql = "SELECT * FROM categories ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    $message = "Database error: " . $conn->error;
    $message_type = "error";
    $categories_result = null;
} else {
    $stmt->execute();
    $categories_result = $stmt->get_result();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Categories Management</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #ec4899;
            --background: #f0f4ff;
            --text: #1e1e2f;
            --input-border: #d1d5db;
            --error-color: #e02424;
            --warning-color: #f59e0b; /* New warning color */
            --info-color: #3b82f6; /* New info color */
            --card-bg: #ffffff;
            --shadow-light: rgba(99, 102, 241, 0.15);
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--background);
            color: var(--text);
            padding: 20px;
            margin: 0;
        }
        h2 {
            margin-bottom: 28px;
            text-align: center;
            color: var(--primary);
            font-weight: 700;
            font-size: 28px;
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
        form {
            margin: 0;
            display: inline-block;
        }
        input[type="text"],
        input[type="number"] {
            padding: 10px 12px;
            margin-right: 8px;
            border: 2px solid var(--input-border);
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            outline: none;
            color: var(--text);
            background-color: #fafafa;
        }
        .action-buttons-container {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 25px;
            align-items: center;
            flex-wrap: wrap; /* Added for responsiveness */
        }

        .action-button {
            padding: 10px 20px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
            transition: background 0.3s ease, box-shadow 0.3s ease, transform 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            white-space: nowrap; /* Prevent text wrapping */
        }
        .action-button:hover {
            background: linear-gradient(90deg, #524ddf, #da3f92);
            box-shadow: 0 6px 15px rgba(99, 102, 241, 0.4);
            transform: translateY(-2px);
        }
        .action-button.secondary { /* Style for the Import button */
            background: linear-gradient(90deg, #28a745, #218838); /* Green gradient */
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
        }
        .action-button.secondary:hover {
            background: linear-gradient(90deg, #218838, #1e7e34);
            box-shadow: 0 6px 15px rgba(40, 167, 69, 0.4);
        }

        .top-level-button {
            min-width: 175px;
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

        td .action-button,
        td form button {
            padding: 6px 12px;
            font-size: 13px;
            margin-right: 8px;
            background: var(--primary);
            box-shadow: none;
            width: auto;
            min-width: unset;
            display: inline-flex;
            height: auto;
        }
        td form button {
            background: #dc3545;
        }

        td .action-button:hover,
        td form button:hover {
            box-shadow: none;
            transform: none;
        }

        .import-section {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
            flex-wrap: wrap; /* Added for responsiveness */
        }
        .import-section input[type="file"] {
            border: 2px solid var(--input-border);
            padding: 8px;
            border-radius: 8px;
            background-color: #fafafa;
            color: var(--text);
            font-size: 15px;
        }

        @media (max-width: 768px) {
            .action-buttons-container, .import-section {
                flex-direction: column;
                align-items: center;
            }
            .top-level-button {
                width: 80%;
                min-width: unset;
            }
            .import-section input[type="file"] {
                width: 80%; /* Adjust file input width for smaller screens */
                max-width: 300px;
            }
        }
    </style>
</head>
<body>
    <h2>Admin: Manage Categories</h2>

    <?php if ($message): ?>
        <div class="message <?php if (isset($message_type)) echo $message_type; ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="action-buttons-container">
        <a href="add_category.php" class="action-button top-level-button">Add New Category</a>
        <button type="button" class="action-button top-level-button" onclick="downloadSelectedCategories()">Download Selected</button>
    </div>

    <div class="import-section">
        <h3>Import Categories (CSV):</h3>
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="file" name="csv_file" class="dropify" data-allowed-file-extensions="csv xls xlsx" data-max-file-size="5M" accept=".csv, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            <button type="submit" class="action-button secondary">Import</button>
        </form>
    </div>

    <?php if ($categories_result && $categories_result->num_rows > 0): ?>
        <table id="categories-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>Category ID</th>
                    <th>Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($cat = $categories_result->fetch_assoc()): ?>
                    <tr>
                        <td><input type="checkbox" class="row-checkbox" value="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name']) ?>"></td>
                        <td><?= $cat['id'] ?></td>
                        <td><?= htmlspecialchars($cat['name']) ?></td>
                        <td>
                            <a href="edit_category.php?id=<?= $cat['id'] ?>" class="action-button">Edit</a>
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                <button type="submit" onclick="return confirm('Are you sure you want to delete this category?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <div style="text-align: center; margin-top: 20px;">
            <form method="POST">
                <input type="hidden" name="action" value="bulk_delete">
                <input type="hidden" name="selected_ids[]" id="bulk-delete-ids">
                <button type="submit" class="action-button" style="background: #dc3545;" onclick="return confirmBulkDelete()">Bulk Delete Selected</button>
            </form>
        </div>
    <?php else: ?>
        <p style="text-align:center;">No categories found.</p>
    <?php endif; ?>
    <script src="js/dropify.min.js"></script>
    
    <script>
        $(document).ready(function(){
          $('.dropify').dropify();
        });
        $(document).ready(function () {
            $('#categories-table').DataTable({
                paging: true,
                info: true,
                ordering: true,
                searching: true,
                pageLength: 5,
                lengthChange: false,
                language: {
                    emptyTable: "No categories found."
                }
            });

            $('#select-all').on('click', function () {
                $('.row-checkbox').prop('checked', this.checked);
            });

            // Update bulk delete IDs when checkboxes change
            $(document).on('change', '.row-checkbox, #select-all', function() {
                const selectedIds = Array.from(document.querySelectorAll('.row-checkbox:checked'))
                                     .map(cb => cb.value);
                $('#bulk-delete-ids').val(selectedIds.join(','));
            });
        });

        function downloadSelectedCategories() {
            const checkboxes = document.querySelectorAll('.row-checkbox:checked');
            if (checkboxes.length === 0) {
                alert("Please select at least one category to download.");
                return;
            }
            let csv = "Category ID,Name\n";
            checkboxes.forEach(cb => {
                const row = cb.closest("tr");
                // DataTables might reorder columns, so get by data attribute if possible, or by fixed index.
                // For simplicity, let's assume the order in HTML remains consistent for now.
                const id = row.children[1].textContent.trim();
                const name = row.children[2].textContent.trim();
                csv += `"${id}","${name}"\n`;
            });
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "selected_categories.csv";
            document.body.appendChild(link); 
            link.click();
            document.body.removeChild(link); 

        function confirmBulkDelete() {
            const selectedIds = $('#bulk-delete-ids').val();
            if (selectedIds === '') {
                alert("Please select categories to perform bulk deletion.");
                return false; 
            }
            return confirm("Are you sure you want to delete the selected categories?");
        }
    </script>
</body>
</html>