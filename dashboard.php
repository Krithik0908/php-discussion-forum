<?php include 'header.php'; ?>

<div class="main">
<?php
if (isset($_GET['page']) && $_SESSION['role'] === 'admin') {
    $page = $_GET['page'];
    if ($page === 'admin_users') {
        include 'admin_users.php';
    } elseif ($page === 'admin_categories') {
        include 'admin_categories.php';
    } elseif ($page === 'admin_posts') {
        include 'admin_posts.php';
    } elseif ($page === 'admin_replies') {
        include 'admin_replies.php';
    } else {
        echo "<p>Invalid admin page.</p>";
    }
} else {
?>

    <h2>Search Questions</h2>
    <form method="GET" action="dashboard.php" enctype="multipart/form-data">
        <input type="text" name="search" placeholder="Search by keyword..." style="width: 70%;" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
        <button type="submit">Search</button>
    </form>

    <h2>Ask a Question</h2>

    <?php if ($logged_in): ?>
        <button onclick="showAskModal()">New Question</button>
    <?php else: ?>
        <p><a href="login.php"><i>Ask a question</i></a></p>
    <?php endif; ?>

    <div id="askModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideAskModal()">&times;</span>
            <h3>Ask a Question</h3>
            <form method="POST" action="post_question.php" enctype="multipart/form-data">
                <select name="category_id" required style="width: 100%;">
                    <option value="">Select Category</option>
                    <?php
                    $cat_result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
                    while ($cat = $cat_result->fetch_assoc()):
                    ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endwhile; ?>
                </select><br>

                <input type="text" name="title" placeholder="Question title" required style="width: 100%;"><br>
                <textarea name="body" placeholder="Describe your question..." style="width: 100%;" required></textarea><br>

                <label>Upload an image (optional):</label>
                <input type="file" name="image" accept="image/*"><br>

                <button type="submit">Post Question</button>
            </form>
        </div>
    </div>

<?php
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    $view = isset($_GET['view']) ? $_GET['view'] : '';
    $current_user = $logged_in ? $_SESSION['username'] : '';


if ($view === 'my_questions' && $logged_in) {
    $stmt = $conn->prepare("SELECT * FROM questions WHERE username = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $current_user);
    $stmt->execute();
    $q_result = $stmt->get_result();

} elseif ($view === 'my_answers' && $logged_in) {
    $stmt = $conn->prepare("SELECT q.*, a.created_at AS answer_created FROM questions q JOIN answers a ON q.id = a.question_id WHERE a.username = ? AND q.status = 'approved' ORDER BY a.created_at DESC");
    $stmt->bind_param("s", $current_user);
    $stmt->execute();
    $q_result = $stmt->get_result();

} elseif ($view === 'my_replies' && $logged_in) {

    $stmt = $conn->prepare("
        SELECT a.*, r.created_at as reply_created
        FROM answers a
        JOIN replies r ON a.id = r.answer_id
        JOIN questions q ON a.question_id = q.id
        WHERE r.username = ? AND q.status = 'approved'
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param("s", $current_user);
    $stmt->execute();
    $q_result = $stmt->get_result();

} elseif (!empty($search)) {
    $likeSearch = "%$search%";
    if ($category_filter > 0) {
        $stmt = $conn->prepare("SELECT * FROM questions WHERE status = 'approved' AND category_id = ? AND (title LIKE ? OR body LIKE ?) ORDER BY created_at DESC");
        $stmt->bind_param("iss", $category_filter, $likeSearch, $likeSearch);
    } else {
        $stmt = $conn->prepare("SELECT * FROM questions WHERE status = 'approved' AND (title LIKE ? OR body LIKE ?) ORDER BY created_at DESC");
        $stmt->bind_param("ss", $likeSearch, $likeSearch);
    }
    $stmt->execute();
    $q_result = $stmt->get_result();

} else {
    if ($category_filter > 0) {
        $stmt = $conn->prepare("SELECT * FROM questions WHERE status = 'approved' AND category_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $category_filter);
        $stmt->execute();
        $q_result = $stmt->get_result();
    } else {
        $q_result = $conn->query("SELECT * FROM questions WHERE status = 'approved' ORDER BY created_at DESC");
    }
}

    $question_ids = [];
    if ($q_result) {
        while ($q = $q_result->fetch_assoc()) {
            $question_ids[] = $q;
        }
    } else {
        $question_ids = [];
    }

    $counts = [];
    if (!empty($question_ids)) {
        $ids = array_column($question_ids, 'id');
        $in = implode(',', array_map('intval', $ids));

        $count_query = "
            SELECT
                questions.id AS question_id,
                COUNT(DISTINCT answers.id) AS answer_count,
                COUNT(replies.id) AS reply_count
            FROM questions
            LEFT JOIN answers ON answers.question_id = questions.id
            LEFT JOIN replies ON replies.answer_id = answers.id
            WHERE questions.id IN ($in)
            GROUP BY questions.id
        ";
        $count_result = $conn->query($count_query);

        while ($row = $count_result->fetch_assoc()) {
            $counts[$row['question_id']] = [
                'answer_count' => $row['answer_count'],
                'reply_count' => $row['reply_count']
            ];
        }
    }
?>

    <h2>Recent Questions</h2>
    <?php foreach ($question_ids as $q): ?>
        <?php
            $questionUserPic = getProfilePic($conn, $q['username']);
        ?>
        <div class="question" style="display: flex; gap: 15px;">
            <a href="profile.php?user=<?= urlencode($q['username']) ?>">
                <img src="<?= htmlspecialchars($questionUserPic) ?>" alt="<?= htmlspecialchars($q['username']) ?>'s profile picture" style="width:50px; height:50px; border-radius: 50%; object-fit: cover;">
            </a>
            <div>
                <strong><?= htmlspecialchars($q['title']) ?></strong><br>
                <small class="meta">Posted by
                    <a href="profile.php?user=<?= urlencode($q['username']) ?>"><?= htmlspecialchars($q['username']) ?></a> on <?= $q['created_at'] ?>
                </small>
                <p><?= nl2br(htmlspecialchars($q['body'])) ?></p>

                <?php if (!empty($q['image']) && file_exists("uploads/" . $q['image'])): ?>
                    <img src="uploads/<?= htmlspecialchars($q['image']) ?>" alt="Question image" class="uploaded-image">
                <?php endif; ?>

                <p><strong>Answers:</strong> <?= $counts[$q['id']]['answer_count'] ?? 0 ?> | <strong>Replies:</strong> <?= $counts[$q['id']]['reply_count'] ?? 0 ?></p>

                <?php
                $a_stmt = $conn->prepare("SELECT * FROM answers WHERE question_id=? ORDER BY created_at ASC");
                $a_stmt->bind_param("i", $q['id']);
                $a_stmt->execute();
                $a_result = $a_stmt->get_result();

                if ($a_result->num_rows > 0): ?>
                    <h4 style="margin-top: 30px;">Answers</h4>
                    <?php while ($a = $a_result->fetch_assoc()):
                        $answerUserPic = getProfilePic($conn, $a['username']);
                    ?>
                        <div class="answer" style="display: flex; gap: 15px; margin-bottom: 30px;">
                            <a href="profile.php?user=<?= urlencode($a['username']) ?>">
                                <img src="<?= htmlspecialchars($answerUserPic) ?>" alt="<?= htmlspecialchars($a['username']) ?>'s profile picture" style="width:40px; height:40px; border-radius: 50%; object-fit: cover;">
                            </a>
                            <div>
                                <small class="meta">Answered by
                                    <a href="profile.php?user=<?= urlencode($a['username']) ?>"><?= htmlspecialchars($a['username']) ?></a> on <?= $a['created_at'] ?>
                                </small>
                                <p><?= nl2br(htmlspecialchars($a['body'])) ?></p>

                                <?php if (!empty($a['image']) && file_exists("uploads/" . $a['image'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($a['image']) ?>" alt="Answer image" class="uploaded-image">
                                <?php endif; ?>

                                <?php
                                $r_stmt = $conn->prepare("SELECT * FROM replies WHERE answer_id=? ORDER BY created_at ASC");
                                $r_stmt->bind_param("i", $a['id']);
                                $r_stmt->execute();
                                $r_result = $r_stmt->get_result();

                                if ($r_result->num_rows > 0): ?>
                                    <h5>Replies</h5>
                                    <?php while ($r = $r_result->fetch_assoc()):
                                        $replyUserPic = getProfilePic($conn, $r['username']);
                                    ?>
                                        <div class="reply" style="display: flex; gap: 10px; align-items: center;">
                                            <a href="profile.php?user=<?= urlencode($r['username']) ?>">
                                                <img src="<?= htmlspecialchars($replyUserPic) ?>" alt="<?= htmlspecialchars($r['username']) ?>'s profile picture" style="width:30px; height:30px; border-radius: 50%; object-fit: cover;">
                                            </a>
                                            <div>
                                                <small class="meta">Replied by
                                                    <a href="profile.php?user=<?= urlencode($r['username']) ?>"><?= htmlspecialchars($r['username']) ?></a> on <?= $r['created_at'] ?>
                                                </small>
                                                <p><?= nl2br(htmlspecialchars($r['body'])) ?></p>



                                                <?php if (!empty($r['image']) && file_exists("uploads/" . $r['image'])): ?>
                                                    <img src="uploads/<?= htmlspecialchars($r['image']) ?>" alt="Reply image" class="uploaded-image">
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php endif; ?>

                                <?php if ($logged_in): ?>
                                    <button onclick="showReplyForm(<?= $a['id'] ?>)">Reply</button>
                                    <div id="replyForm-<?= $a['id'] ?>" style="display:none; margin-top: 10px;">
                                        <form method="POST" action="post_reply.php" enctype="multipart/form-data">
                                            <input type="hidden" name="answer_id" value="<?= $a['id'] ?>">
                                            <textarea name="body" placeholder="Write a reply..." required style="width: 100%;"></textarea><br>

                                            <label>Upload an image (optional):</label>
                                            <input type="file" name="image" accept="image/*"><br>

                                            <button type="submit">Post Reply</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <?php if ($logged_in): ?>
                        <h4>No answers yet. Be the first to answer:</h4>
                        <form method="POST" action="post_answer.php" enctype="multipart/form-data">
                            <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                            <textarea name="body" placeholder="Write your answer..." required style="width: 100%;"></textarea><br>

                            <label>Upload an image (optional):</label>
                            <input type="file" name="image" accept="image/*"><br>

                            <button type="submit">Post Answer</button>
                        </form>
                    <?php else: ?>
                        <p><a href="login.php"><i>Log in to answer</i></a></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php }  ?>
 </div>

<?php include 'footer.php'; ?>