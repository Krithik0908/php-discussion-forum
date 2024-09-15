<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$loggedInUser = $_SESSION['username'];
$logged_in = !empty($loggedInUser);
$username = isset($_GET['user']) ? $_GET['user'] : $loggedInUser;


$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "User not found.";
    exit;
}

$isOwner = ($loggedInUser === $username);


function getUserProfilePic($conn, $username) {
    $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $u = $res->fetch_assoc();
    if ($u && !empty($u['profile_pic']) && file_exists("uploads/" . $u['profile_pic'])) {
        return "uploads/" . rawurlencode($u['profile_pic']);
    }
    return "uploads/default.jpg";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Profile of <?= htmlspecialchars($user['username']) ?></title>
   

    <style>
       
:root {
    --primary: #6366f1;
    --secondary: #ec4899;
    --background: #f0f4ff;
    --text: #1e1e2f;
    --card-bg: #ffffff;
    --border: #d1d5db;
    --radius: 8px;
    --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
}

.header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: linear-gradient(to right, var(--primary), var(--secondary));
    color: white;
    display: flex;
    align-items: center;
    padding: 0 24px;
    z-index: 1000;
    box-shadow: var(--shadow);
}

.header .logo {
    height: 42px;
    margin-right: 16px;
}

.header .site-title {
    font-size: 24px;
    font-weight: 600;
}


.sidebar {
    position: fixed;
    top: 60px;
    left: 0;
    width: 240px;
    height: calc(100vh - 60px);
    background: linear-gradient(to bottom, #ffffff, #f9fafb);
    border-right: 2px solid var(--border);
    padding: 24px;
    overflow-y: auto;
    box-shadow: 4px 0 10px rgba(0, 0, 0, 0.05);
    transition: background 0.3s ease;
}

.sidebar h3, .sidebar h4 {
    margin-bottom: 16px;
    font-weight: 600;
}

.sidebar p, .sidebar a {
    display: block;
    margin-bottom: 12px;
    padding: 8px 12px;
    color: var(--primary);
    text-decoration: none;
    border-radius: var(--radius);
    transition: background 0.2s, color 0.2s;
}

.sidebar a:hover {
    background: var(--primary);
    color: white;
}

.sidebar ul {
    list-style: none;
    padding: 0;
}

.sidebar ul li {
    margin-bottom: 6px;
}

.sidebar ul li a {
    color: var(--text);
    transition: color 0.3s ease;
}

.sidebar ul li a:hover {
    color: var(--primary);
}


.main {
    margin-left: 240px;
    padding: 80px 32px 32px;
    min-height: 100vh;
    background: var(--card-bg);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: auto;
    transition: background 0.3s ease;
}

.profile-pic-wrapper {
    position: relative;
    display: inline-block;
    margin-bottom: 16px;
}

img.profile-pic {
    border-radius: 50%;
    width: 100px;
    height: 100px;
    object-fit: cover;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    transition: transform 0.3s ease;
    cursor: pointer;
}

img.profile-pic:hover {
    transform: scale(1.05);
}

.profile-pic-edit-icon {
    position: absolute;
    bottom: 6px;
    right: 6px;
    background: rgba(0,0,0,0.6);
    color: #fff;
    font-size: 18px;
    padding: 6px;
    border-radius: 50%;
    cursor: pointer;
    transition: background 0.3s;
}

.profile-pic-edit-icon:hover {
    background: rgba(0,0,0,0.8);
}


.editable-field {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    max-width: 400px;
}

.editable-field input[readonly] {
    background: #f9fafb;
    border: 2px solid var(--border);
    padding: 10px 12px;
    border-radius: var(--radius);
    width: 100%;
    font-size: 15px;
    color: var(--text);
    transition: border-color 0.3s;
}

.editable-field input:not([readonly]) {
    border-color: var(--primary);
    background: white;
}

.edit-icon {
    cursor: pointer;
    font-size: 18px;
    color: var(--primary);
    transition: color 0.3s;
}

.edit-icon:hover {
    color: var(--secondary);
}

input, textarea, select, button {
    font-family: 'Poppins', sans-serif;
    font-size: 15px;
    padding: 12px 14px;
    margin-top: 12px;
    border-radius: var(--radius);
    border: 2px solid var(--border);
    transition: border-color 0.3s;
    outline: none;
    color: var(--text);
}

input:focus, textarea:focus, select:focus {
    border-color: var(--primary);
}

button {
    background: linear-gradient(to right, var(--primary), var(--secondary));
    color: #fff;
    font-weight: bold;
    border: none;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    transition: opacity 0.3s ease;
}

button:hover {
    opacity: 0.9;
}


body::-webkit-scrollbar,
.sidebar::-webkit-scrollbar,
.main::-webkit-scrollbar {
    width: 10px;
}

body::-webkit-scrollbar-track,
.sidebar::-webkit-scrollbar-track,
.main::-webkit-scrollbar-track {
    background: #e5e7eb;
    border-radius: 6px;
}

body::-webkit-scrollbar-thumb,
.sidebar::-webkit-scrollbar-thumb,
.main::-webkit-scrollbar-thumb {
    background: linear-gradient(var(--primary), var(--secondary));
    border-radius: 6px;
    border: 2px solid #f0f4ff;
    transition: background 0.3s ease;
}

body::-webkit-scrollbar-thumb:hover,
.sidebar::-webkit-scrollbar-thumb:hover,
.main::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(var(--secondary), var(--primary));
}


@media (max-width: 768px) {
    .sidebar {
        display: none;
    }

    .main {
        margin-left: 0;
        padding: 80px 16px 16px;
        border-radius: 0;
        box-shadow: none;
    }
}


    </style>
    <script>
        function toggleProfilePicInput() {
    const container = document.getElementById('profile-pic-input-container');
    if (container.style.display === 'none' || container.style.display === '') {
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
}

        function enableEdit(id) {
            const input = document.getElementById(id);
            if (input.hasAttribute('readonly')) {
                input.removeAttribute('readonly');
                input.focus();
            }
        }
    </script>
</head>
<body>
    <div class="header">
        <img src="logo of discussion forum.jpg" alt="Site Logo" class="logo">
        <span class="site-title">Discussion Forum</span>
    </div>

    <div class="sidebar">
        <h3>Dashboard</h3>
        <?php if ($logged_in): ?>
            <p>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></p>
            <a href="logout.php">Logout</a>
        <?php endif; ?>
        <a href="dashboard.php">All Questions</a>
        <?php if ($logged_in): ?>
            <a href="dashboard.php?view=my_questions">My Questions</a>
            <a href="dashboard.php?view=my_answers">My Answers</a>
            <a href="dashboard.php?view=my_replies">My Replies</a>
        <?php endif; ?>

        <h4>Categories</h4>
        <ul>
            <?php
            $cat_count = $conn->query("
                SELECT c.id, c.name, COUNT(q.id) as count
                FROM categories c
                LEFT JOIN questions q ON q.category_id = c.id
                GROUP BY c.id, c.name
                ORDER BY c.name
            ");
            while ($row = $cat_count->fetch_assoc()):
            ?>
                <li><a href="dashboard.php?category=<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?> (<?= $row['count'] ?>)</a></li>
            <?php endwhile; ?>
        </ul>
    </div>

    <div class="main">
        <h2>Profile of <?= htmlspecialchars($user['username']) ?></h2>

      <div class="profile-pic-wrapper">
    <?php if (!empty($user['profile_pic']) && file_exists("uploads/" . $user['profile_pic'])): ?>
        <img src="uploads/<?= rawurlencode($user['profile_pic']) ?>" alt="Profile Picture" class="profile-pic">
    <?php else: ?>
        <img src="uploads/default.jpg" alt="Default Profile Picture" class="profile-pic">
    <?php endif; ?>

    <?php if ($isOwner): ?>
        <span class="profile-pic-edit-icon" onclick="toggleProfilePicInput()" title="Change Profile Picture">&#9998;</span>
    <?php endif; ?>
</div>
        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p><br>
        <p><strong>Phone Number:</strong> <?= htmlspecialchars($user['mobile_number']) ?></p><br><br>
        <p><strong>Points:</strong> <?= (int)$user['points'] ?></p><br>


        <?php if ($isOwner): ?>
            <h3>Edit Your Profile</h3>
            <form method="POST" action="update_profile.php" enctype="multipart/form-data">
                 <label>Email:</label><br>
                <div class="editable-field">
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                    <span class="edit-icon" onclick="enableEdit('email')" title="Edit Email">&#9998;</span>
                </div>
                <label>Mobile Number:</label>
                <div class="editable-field">
                    <input type="number" id="mobile_number" name="mobile_number" value="<?= htmlspecialchars($user['mobile_number']) ?>" readonly>
                    <span class="edit-icon" onclick="enableEdit('mobile_number')" title="Edit Phone Number">&#9998;</span>
                </div>
                  
                  <div id="profile-pic-input-container" style="display:none; margin-top:10px;">
                      <input type="file" name="profile_pic" accept="image/*" id="profile_pic_input">
                    </div>
                <label>Password:<label>
                <div class="editable-field">
                    <input type="password" id="password" name="password" value="<?= htmlspecialchars($user['password']) ?>" readonly>
                    <span class="edit-icon" onclick="enableEdit('password')" title="Edit Password">&#9998;</span>
                </div>

                <button type="submit">Update Profile</button>
            </form>
            <a href="dashboard.php">Go to Dashboard</a>
        <?php endif; ?>
    </div>
</body>
</html>
