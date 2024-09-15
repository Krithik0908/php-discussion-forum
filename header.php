<?php
session_start();
include 'db.php';

$logged_in = isset($_SESSION['username']);

function getProfilePic($conn, $username) {
    $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($profile_pic);
    if ($stmt->fetch() && !empty($profile_pic) && file_exists("uploads/" . $profile_pic)) {
        $stmt->close();
        return "uploads/" . $profile_pic;
    } else {
        $stmt->close();
        return "uploads/default.jpg";
    }
}


$current_admin_page = $_GET['page'] ?? '';
$current_dashboard_view = $_GET['view'] ?? '';
$current_category_id = $_GET['category'] ?? '';


?>

<!DOCTYPE html>
<html>
<head>
    <title>Discussion Forum</title>
    <link rel="stylesheet" href="style.css">

    <style>
:root {
    --primary: #6366f1;
    --secondary: #ec4899;
    --background: #f0f4ff;
    --text: #1e1e2f;
    --card-bg: #ffffff;
    --border: #d1d5db;
    --shadow-light: rgba(99, 102, 241, 0.12);
    --shadow-dark: rgba(0, 0, 0, 0.06);
    --transition-speed: 0.25s;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body, html {
    height: 100%;
    font-family: 'Poppins', sans-serif;
    background: var(--background);
    color: var(--text);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}


.header {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 60px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    color: white;
    display: flex;
    align-items: center;
    padding: 0 20px;
    z-index: 10000;
    box-shadow: 0 3px 8px var(--shadow-light);
}

.header .logo {
    height: 38px;
    margin-right: 12px;
}

.header .site-title {
    font-size: 22px;
    font-weight: 600;
}


.avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 2px solid white;
    object-fit: cover;
    margin-left: auto;
}


.sidebar {
    position: fixed;
    top: 60px;
    left: 0;
    width: 220px;
    height: calc(100vh - 60px);
    background: linear-gradient(to bottom, #ffffff, #f3f4f6);
    border-right: 1px solid var(--border);
    padding: 20px 18px;
    overflow-y: auto;
    box-shadow: 3px 0 8px var(--shadow-light);
}

.sidebar h3, .sidebar h4 {
    margin-bottom: 12px;
    color: #111827;
    font-weight: 600;
}

.sidebar a {
    display: block;
    padding: 8px 14px;
    border-radius: 6px;
    background: transparent;
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    margin-bottom: 10px;
    transition: background var(--transition-speed), color var(--transition-speed);
}

.sidebar a:hover,
.sidebar a:focus {
    background: var(--primary);
    color: white;
}

.sidebar a.active {
    background: var(--primary);
    color: white;
    font-weight: 600;
}


.main {
    margin-left: 220px;
    padding: 80px 24px 24px 24px;
    min-height: 100vh;
    background: var(--card-bg);
    overflow: auto;
    border-radius: 8px 0 0 8px;
    box-shadow: 0 2px 8px var(--shadow-dark);
    transition: background 0.3s ease, padding 0.2s ease;
}


button, input, textarea, select {
    padding: 10px 12px;
    margin-top: 12px;
    font-size: 14px;
    border: 1.8px solid var(--border);
    border-radius: 6px;
    outline: none;
    transition: border-color var(--transition-speed);
}

button {
    padding: 6px 12px;
    font-size: 13px;
    max-width: 160px;
    width: fit-content;
    white-space: nowrap;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    color: white;
    font-weight: 600;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    box-shadow: 0 2px 4px var(--shadow-light);
    user-select: none;
    transition: opacity 0.3s ease, box-shadow 0.3s ease, transform 0.2s ease;
}
button:hover,
button:focus {
    opacity: 0.95;
    box-shadow: 0 0 10px var(--primary), 0 0 16px var(--secondary);
    transform: scale(1.02);
}


@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

input:focus, textarea:focus, select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 6px var(--primary);
}


.question {
    border-bottom: 2px dashed #e5e7eb;
    padding: 16px 0;
    margin-bottom: 24px;
    background: #fefeff;
    border-radius: 6px;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.question:hover {
    box-shadow: 0 4px 12px var(--shadow-light);
    transform: translateY(-2px);
}

.answer {
    margin-left: 20px;
    border-left: 4px solid var(--primary);
    padding-left: 12px;
    margin-bottom: 24px;
    background: #f0f5ff;
    border-radius: 6px;
    font-size: 14px;
    line-height: 1.5;
}

.reply {
    margin-left: 20px;
    color: var(--secondary);
    margin-top: 12px;
    margin-bottom: 12px;
    font-style: italic;
    font-size: 13px;
}


.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(3px);
}

.modal-content {
    background: var(--card-bg);
    margin: 12% auto;
    padding: 24px 28px;
    width: 90%;
    max-width: 460px;
    border-radius: 12px;
    position: relative;
    box-shadow: 0 5px 18px var(--shadow-dark);
}

.close {
    position: absolute;
    top: 14px;
    right: 18px;
    font-size: 24px;
    cursor: pointer;
    color: var(--secondary);
    transition: color 0.2s ease;
}

.close:hover {
    color: var(--primary);
}


.uploaded-image {
    margin-top: 10px;
    max-width: 100%;
    height: auto;
    border: 2px solid var(--primary);
    border-radius: 8px;
    box-shadow: 0 2px 6px var(--shadow-light);
}


::-webkit-scrollbar {
    width: 8px;
}
::-webkit-scrollbar-track {
    background: #f3f4f6;
}
::-webkit-scrollbar-thumb {
    background-color: var(--primary);
    border-radius: 6px;
}


@media (max-width: 900px) {
    .sidebar {
        width: 60px;
        padding: 20px 8px;
        overflow-x: hidden;
    }

    .sidebar a {
        font-size: 13px;
        padding: 6px 8px;
    }

    .main {
        margin-left: 60px;
        padding: 80px 18px 18px 18px;
    }

    .header .site-title {
        font-size: 18px;
    }
}

@media (max-width: 500px) {
    .header {
        padding: 0 16px;
    }

    .header .site-title {
        font-size: 16px;
    }

    .modal-content {
        padding: 20px 24px;
        width: 95%;
    }
}

* {
    transition: background 0.3s ease, color 0.3s ease, transform 0.2s ease;
}


.header .logo:hover {
    transform: scale(1.1);
    transition: transform 0.3s ease;
}

.sidebar a {
    position: relative;
    overflow: hidden;
}
.sidebar a::after {
    content: "";
    position: absolute;
    height: 100%;
    width: 0;
    left: 0;
    top: 0;
    background: var(--primary);
    opacity: 0.1;
    transition: width 0.3s ease;
    z-index: 0;
}
.sidebar a:hover::after {
    width: 100%;
}
.sidebar a span,
.sidebar a {
    position: relative;
    z-index: 1;
}


button:hover {
    box-shadow: 0 0 12px var(--primary), 0 0 20px var(--secondary);
    animation: glow 0.6s ease-in-out;
}
@keyframes glow {
    0% { box-shadow: 0 0 0 var(--primary); }
    50% { box-shadow: 0 0 10px var(--secondary); }
    100% { box-shadow: 0 0 0 var(--primary); }
}


.question:hover {
    transform: scale(1.01);
    background: #fefeff;
    border-color: var(--primary);
}


.modal {
    animation: fadeIn 0.3s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}


::-webkit-scrollbar {
    width: 10px;
}
::-webkit-scrollbar-track {
    background: #f3f4f6;
    border-radius: 8px;
}
::-webkit-scrollbar-thumb {
    background: linear-gradient(var(--primary), var(--secondary));
    border-radius: 10px;
    border: 2px solid var(--background);
}
::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(var(--secondary), var(--primary));
}


.uploaded-image:hover {
    transform: scale(1.01);
    box-shadow: 0 4px 12px var(--shadow-light);
}

    </style>
</head>
<body>


<div class="header">
    <img src="logo of discussion forum.jpg" alt="Site Logo" class="logo">
    <span class="site-title">Discussion Forum</span>

    <?php if ($logged_in): ?>
        <div style="margin-left: auto; display: flex; align-items: center; gap: 14px;">
            <span style="font-weight: 500;">
                <?= htmlspecialchars($_SESSION['username']) ?>
            </span>
            <img src="<?= getProfilePic($conn, $_SESSION['username']) ?>" alt="Profile Picture" class="avatar">
            <a href="logout.php" title="Logout"
               style="
                    font-size: 26px;
                    color: white;
                    text-decoration: none;
                    display: flex;
                    align-items: center;
                    padding: 4px 8px;
                    border-radius: 6px;
                    transition: background 0.3s ease;
               "
               onmouseover="this.style.background='rgba(255,255,255,0.15)'"
               onmouseout="this.style.background='transparent'"
            >⎋</a>
        </div>
    <?php endif; ?>
</div>

<div class="sidebar">
   <a href="dashboard.php" class="sidebar-dashboard <?= ($current_admin_page === '' && $current_dashboard_view === '' && $current_category_id === '') ? 'active' : '' ?>"><h3>Dashboard</h3></a>

    <a href="dashboard.php" class="<?= ($current_admin_page === '' && $current_dashboard_view === '' && $current_category_id === '') ? 'active' : '' ?>">All Questions</a>
    <?php if ($logged_in): ?>
    <a href="dashboard.php?view=my_questions" class="<?= ($current_dashboard_view === 'my_questions') ? 'active' : '' ?>">My Questions</a>
    <a href="dashboard.php?view=my_answers" class="<?= ($current_dashboard_view === 'my_answers') ? 'active' : '' ?>">My Answers</a>
    <a href="dashboard.php?view=my_replies" class="<?= ($current_dashboard_view === 'my_replies') ? 'active' : '' ?>">My Replies</a>
<?php endif; ?>

    <h4>Categories</h4>
    <ul>
    <?php
    $cat_stmt = $conn->query("SELECT c.id, c.name, COUNT(q.id) AS count FROM categories c LEFT JOIN questions q ON q.category_id = c.id AND q.status = 'approved' GROUP BY c.id, c.name ORDER BY c.name");
    while ($row = $cat_stmt->fetch_assoc()):
    ?>
        <li><a href="dashboard.php?category=<?= $row['id'] ?>" class="<?= ($current_category_id == $row['id']) ? 'active' : '' ?>"><?= htmlspecialchars($row['name']) ?> (<?= $row['count'] ?>)</a></li>
    <?php endwhile; ?>
    </ul>

    <?php if ($logged_in && in_array($_SESSION['role'], ['admin', 'operator'])): ?>
    <h4>Admin Panel</h4>
    <ul>
        <li><a href="dashboard.php?page=admin_users" class="<?= ($current_admin_page === 'admin_users') ? 'active' : '' ?>">Manage Users</a></li>
        <li><a href="dashboard.php?page=admin_categories" class="<?= ($current_admin_page === 'admin_categories') ? 'active' : '' ?>">Manage Categories</a></li>
        <li><a href="dashboard.php?page=admin_posts" class="<?= ($current_admin_page === 'admin_posts') ? 'active' : '' ?>">Manage Posts</a></li>
        <li><a href="dashboard.php?page=admin_replies" class="<?= ($current_admin_page === 'admin_replies') ? 'active' : '' ?>">Manage Replies</a></li>
    </ul>
    <?php endif; ?>

</div>
