<?php
require "db.php";
require "auth.php";

$userId = $_SESSION["user_id"];

if ($_POST["post"] ?? false) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        die('CSRF token mismatch');
    }
    $stmt = $db->prepare("INSERT INTO posts (user_id, content) VALUES (?,?)");
    $stmt->execute([$userId, trim($_POST["post"])]);
}

if (isset($_POST["like"])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        die('CSRF token mismatch');
    }
    $postId = $_POST["like"];
    $existing = $db->prepare("SELECT * FROM likes WHERE user_id = ? AND post_id = ?");
    $existing->execute([$userId, $postId]);
    if ($existing->fetch()) {
        $db->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?")->execute([$userId, $postId]);
    } else {
        $db->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)")->execute([$userId, $postId]);
    }
    header("Location: index.php");
    exit;
}

if ($_POST["comment"] ?? false) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        die('CSRF token mismatch');
    }
    $stmt = $db->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->execute([$_POST["post_id"], $userId, trim($_POST["comment"])]);
    header("Location: index.php");
    exit;
}

$posts = $db->prepare("
SELECT posts.*, users.username FROM posts
JOIN users ON users.id = posts.user_id
WHERE user_id = ? OR user_id IN (
    SELECT following_id FROM follows WHERE follower_id = ?
)
ORDER BY created_at DESC
");
$posts->execute([$userId, $userId]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social - Home</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Social</h1>
            <nav class="nav-links">
                <a href="profile.php">My Profile</a>
                <a href="search.php">Search</a>
                <a href="messages.php">Messages</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <form method="post" class="post-form">
            <input type="hidden" name="csrf_token" value="<?=generateCSRFToken()?>">
            <input name="post" placeholder="What's happening?" required>
            <button>Post</button>
        </form>

        <?php foreach ($posts as $p): ?>
        <?php
        $postId = $p["id"];
        $likes = $db->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
        $likes->execute([$postId]);
        $likeCount = $likes->fetch()["count"];

        $userLiked = $db->prepare("SELECT * FROM likes WHERE user_id = ? AND post_id = ?");
        $userLiked->execute([$userId, $postId]);
        $isLiked = $userLiked->fetch();

        $comments = $db->prepare("
        SELECT comments.*, users.username FROM comments
        JOIN users ON users.id = comments.user_id
        WHERE post_id = ?
        ORDER BY created_at ASC
        ");
        $comments->execute([$postId]);
        ?>
        <div class="post">
            <div class="username">@<?=htmlspecialchars($p["username"])?></div>
            <div class="content"><?=htmlspecialchars($p["content"])?></div>
            <div class="post-actions">
                <button type="button" onclick="likePost(<?=$postId?>, this)" class="like-btn <?=$isLiked ? 'liked' : ''?>">
                    Like
                </button>
                <span class="like-count">(<?=$likeCount?>)</span>
                <button class="comment-btn" onclick="toggleComments(<?=$postId?>)">Comment</button>
            </div>
            <div id="comments-<?=$postId?>" style="display: none;">
                <form method="post" class="comment-form">
                    <input type="hidden" name="csrf_token" value="<?=generateCSRFToken()?>">
                    <input name="comment" placeholder="Write a comment..." required>
                    <input type="hidden" name="post_id" value="<?=$postId?>">
                    <button id="comment-btn">Comment</button>
                </form>
                <?php foreach ($comments as $c): ?>
                <div class="comment">
                    <div class="username">@<?=htmlspecialchars($c["username"])?></div>
                    <div class="content"><?=htmlspecialchars($c["content"])?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <script>
        function toggleComments(postId) {
            const commentsDiv = document.getElementById('comments-' + postId);
            commentsDiv.style.display = commentsDiv.style.display === 'none' ? 'block' : 'none';
        }

        function likePost(postId, button) {
            const formData = new FormData();
            formData.append('like', postId);
            formData.append('csrf_token', '<?=generateCSRFToken()?>');

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Update like count and button state
                const likeCountSpan = button.nextElementSibling;
                const currentCount = parseInt(likeCountSpan.textContent.match(/\d+/)[0]);
                if (button.classList.contains('liked')) {
                    button.classList.remove('liked');
                    likeCountSpan.textContent = '(' + (currentCount - 1) + ')';
                } else {
                    button.classList.add('liked');
                    likeCountSpan.textContent = '(' + (currentCount + 1) + ')';
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>
