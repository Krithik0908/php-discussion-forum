<?php
include 'db.php';
include 'header.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $category_id = intval($_POST['category_id']);

    if (empty($title)) {
        $message = "Error: Post title cannot be empty.";
    } else {
        $stmt = $conn->prepare("UPDATE questions SET title = ?, category_id = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("sii", $title, $category_id, $post_id);
            if ($stmt->execute()) {
                $message = "Post updated successfully.";
            } else {
                $message = "Error updating post: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "Error preparing statement: " . $conn->error;
        }
    }
}

$post = null;
if ($post_id > 0) {
    $stmt = $conn->prepare("SELECT title, category_id FROM questions WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $post = $result->fetch_assoc();
        $stmt->close();
    } else {
        $message = "Error preparing statement to fetch post: " . $conn->error;
    }
} else {
    $message = "Error: Invalid post ID provided.";
}

$categories = $conn->query("SELECT id, name FROM categories");
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

    .edit-form input,
    .edit-form select {
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

    .no-results {
        color: #888;
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
        <h2>Edit the Post</h2>

        <?php if ($message): ?>
            <div class="message <?= (strpos($message, 'Error') !== false) ? 'error' : 'success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($post): ?>
            <form method="POST" class="edit-form">
                <label for="title">Title</label>
                <input type="text" name="title" id="title" value="<?= htmlspecialchars($post['title']) ?>" required>

                <label for="category_id">Category</label>
                <select name="category_id" id="category_id">
                    <?php
                    if ($categories && $categories->num_rows > 0) {
                        while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $post['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endwhile;
                    } else {
                        echo '<option value="">No categories available</option>';
                    }
                    ?>
                </select>

                <button type="submit" class="action-button">Update Post</button>
            </form>
        <?php else: ?>
            <p class="no-results">Post not found or an error occurred.</p>
        <?php endif; ?>


    </div>
</div>

<?php
include 'footer.php';
?>
