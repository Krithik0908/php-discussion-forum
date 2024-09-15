<?php
include_once 'db.php';
include_once 'header.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = '';
$categoryId = null;
$categoryName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId = intval($_POST['category_id']);
    $newName = trim($_POST['new_category_name']);

    if (empty($newName)) {
        $message = "Error: Category name cannot be empty.";
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
        $stmt_check->bind_param("si", $newName, $categoryId);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows === 0) {
            $stmt_update = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt_update->bind_param("si", $newName, $categoryId);
            if ($stmt_update->execute()) {
                $message = "Category updated successfully.";
                $categoryName = $newName;
            } else {
                $message = "Error updating category.";
            }
            $stmt_update->close();
        } else {
            $message = "Error: Category name already exists.";
        }
        $stmt_check->close();
    }
} elseif (isset($_GET['id'])) {
    $categoryId = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $category = $result->fetch_assoc();
        $categoryName = $category['name'];
    } else {
        $message = "Error: Category not found.";
        $categoryId = null;
    }
    $stmt->close();
} else {
    $message = "Error: No category ID provided.";
}
?>

<style>
    body {
        font-family: Arial, sans-serif;
    }

    .center-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 80vh;
        padding: 20px;
    }

    .admin-content {
        width: 100%;
        max-width: 600px;
        background-color: #f9f9f9;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    h2 {
        margin-bottom: 20px;
        color: #333;
    }

    .edit-form label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #333;
    }

    .edit-form input {
        width: 100%;
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 5px;
        border: 1px solid #ccc;
        box-sizing: border-box;
    }

    .edit-form button.action-button {
        background-color: #6366f1;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }

    .edit-form button.action-button:hover {
        background-color: #4b47c9;
    }

    .message.success {
        color: green;
        margin-bottom: 15px;
    }

    .message.error {
        color: red;
        margin-bottom: 15px;
    }

    .back-link {
        display: inline-block;
        margin-top: 10px;
        text-decoration: none;
        color: #6366f1;
    }

    .back-link:hover {
        text-decoration: underline;
    }
</style>

<div class="center-wrapper">
    <div class="admin-content">
        <h2>Edit Category</h2>

        <?php if ($message): ?>
            <div class="message <?= (strpos($message, 'Error') !== false || strpos($message, 'empty') !== false || strpos($message, 'exists') !== false) ? 'error' : 'success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($categoryId !== null): ?>
            <form method="POST" class="edit-form">
                <input type="hidden" name="category_id" value="<?= htmlspecialchars($categoryId) ?>">

                <label for="new_category_name">Category Name</label>
                <input type="text" id="new_category_name" name="new_category_name" value="<?= htmlspecialchars($categoryName) ?>" required>

                <button type="submit" class="action-button">Update Category</button>
            </form>
        <?php else: ?>
            <p class="no-results">Category not found or an error occurred.</p>
        <?php endif; ?>
    </div>
</div>

<?php include_once 'footer.php'; ?>
