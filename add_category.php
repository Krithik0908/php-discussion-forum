<?php
include_once 'db.php';
include_once 'header.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['category_name']) && !empty(trim($_POST['category_name']))) {
        $name = trim($_POST['category_name']);

        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $stmt_insert = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt_insert->bind_param("s", $name);
            if ($stmt_insert->execute()) {
                $message = "Category added successfully.";
            } else {
                $message = "Error: Failed to add category.";
            }
            $stmt_insert->close();
        } else {
            $message = "Error: Category already exists.";
        }
        $stmt->close();
    } else {
        $message = "Error: Category name cannot be empty.";
    }
}
?>

<style>
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

    form label {
        font-weight: bold;
        margin-bottom: 5px;
        display: block;
    }

    form input[type="text"] {
        width: 100%;
        padding: 10px;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }

    button.action-button {
        background-color: #6366f1;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
    }

    button.action-button:hover {
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
        <h2>Add New Category</h2>

        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'Error') !== false ? 'error' : 'success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label for="category_name">Category Name</label>
            <input type="text" id="category_name" name="category_name" placeholder="Enter category name" required>
            <button type="submit" class="action-button">Add Category</button>
        </form>
    </div>
</div>

<?php include_once 'footer.php'; ?>
