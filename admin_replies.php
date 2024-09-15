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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if (($action === 'toggle_status' || $action === 'bulk_block' || $action === 'bulk_approve') && isset($_POST['reply_ids'])) {
            $replyIds = array_map('intval', $_POST['reply_ids']);
            $statusToSet = ($action === 'bulk_block') ? 'blocked' : 'approved';

            $in = str_repeat('?,', count($replyIds) - 1) . '?';
            $stmt = $conn->prepare("UPDATE replies SET status = ? WHERE id IN ($in)");
            $types = str_repeat('i', count($replyIds));
            $stmt->bind_param(str_repeat('s', 1) . $types, $statusToSet, ...$replyIds);
            $stmt->execute();
            $stmt->close();

            $message = "Selected replies updated.";
        } elseif ($action === 'toggle_status' && isset($_POST['reply_id'])) {
            $replyId = intval($_POST['reply_id']);

            $stmt = $conn->prepare("SELECT status FROM replies WHERE id = ?");
            $stmt->bind_param("i", $replyId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();

            if ($result->num_rows === 1) {
                $reply = $result->fetch_assoc();
                $newStatus = ($reply['status'] === 'approved') ? 'blocked' : 'approved';

                $stmt_update = $conn->prepare("UPDATE replies SET status = ? WHERE id = ?");
                $stmt_update->bind_param("si", $newStatus, $replyId);
                $stmt_update->execute();
                $stmt_update->close();

                $message = "Reply status updated.";
            }
        }
    }
}

$sql = "
    SELECT r.id, r.body, r.username, r.status, r.created_at, a.question_id
    FROM replies r
    JOIN answers a ON r.answer_id = a.id
    ORDER BY r.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$replies_result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin: Manage Replies</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 25px; }
        th, td { border: 1px solid #ccc; padding: 12px; text-align: left; }
        th { background-color: #6366f1; color: white; }
        .approved { background-color: #e6ffe6; }
        .blocked { background-color: #ffe6e6; }
        button { padding: 8px 14px; background-color: #6366f1; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background-color: #4b47c9; }
        .message { margin-bottom: 20px; color: green; }
    </style>
    <script>
        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll('.select-reply');
            checkboxes.forEach(cb => cb.checked = source.checked);
        }

        function downloadSelected() {
            const selectedRows = document.querySelectorAll('.select-reply:checked');
            if (selectedRows.length === 0) return alert("Please select at least one reply.");

            let csv = 'Reply ID,Reply Body,Username,Status,Created At\n';
            selectedRows.forEach(cb => {
                const row = cb.closest('tr');
                const cols = row.querySelectorAll('td');
                csv += [cb.value, cols[1].innerText, cols[2].innerText, cols[3].innerText, cols[4].innerText].join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'selected_replies.csv';
            a.click();
            URL.revokeObjectURL(url);
        }
    </script>
</head>
<body>

<h2>Admin: Manage Replies</h2>
<?php if ($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="POST" id="bulkForm">
    <table>
        <thead>
            <tr>
                <th><input type="checkbox" onclick="toggleSelectAll(this)"></th>
                <th>Reply Body</th>
                <th>User</th>
                <th>Status</th>
                <th>Posted At</th>
                <th>Actions</th>
                <th>Download</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($reply = $replies_result->fetch_assoc()): ?>
            <tr class="<?= htmlspecialchars($reply['status']) ?>">
                <td><input type="checkbox" class="select-reply" name="reply_ids[]" value="<?= $reply['id'] ?>"></td>
                <td><?= nl2br(htmlspecialchars($reply['body'])) ?></td>
                <td><?= htmlspecialchars($reply['username']) ?></td>
                <td><?= ucfirst($reply['status']) ?></td>
                <td><?= htmlspecialchars($reply['created_at']) ?></td>
                <td>
                    <form method="POST" style="display:inline-block;">
                        <input type="hidden" name="reply_id" value="<?= $reply['id'] ?>">
                        <input type="hidden" name="action" value="toggle_status">
                        <button type="submit">
                            <?= $reply['status'] === 'approved' ? 'Block' : 'Approve' ?>
                        </button>
                    </form>
                </td>
                <td><button type="button" onclick="downloadSelected()">Download</button></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div style="margin-top: 15px;">
        <button type="submit" name="action" value="bulk_block">Block Selected</button>
        <button type="submit" name="action" value="bulk_approve">Approve Selected</button>
        <button type="button" onclick="downloadSelected()">Download Selected</button>
    </div>
</form>
</body>
</html>
