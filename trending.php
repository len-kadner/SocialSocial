<?php
require "db.php";
require "auth.php";

$userId = $_SESSION["user_id"];

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
        // Benachrichtigung senden
        $postOwner = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
        $postOwner->execute([$postId]);
        $owner = $postOwner->fetch();
        if ($owner && $owner["user_id"] != $userId) {
            $db->prepare("INSERT INTO notifications (user_id, type, from_user_id, post_id) VALUES (?, 'like', ?, ?)")->execute([$owner["user_id"], $userId, $postId]);
        }
    }
    header("Location: trending.php");
    exit;
}

if ($_POST["comment"] ?? false) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        die('CSRF token mismatch');
    }
    $stmt = $db->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->execute([$_POST["post_id"], $userId, trim($_POST["comment"])]);
    // Benachrichtigung senden
    $postOwner = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
    $postOwner->execute([$_POST["post_id"]]);
    $owner = $postOwner->fetch();
    if ($owner && $owner["user_id"] != $userId) {
        $db->prepare("INSERT INTO notifications (user_id, type, from_user_id, post_id) VALUES (?, 'comment', ?, ?)")->execute([$owner["user_id"], $userId, $_POST["post_id"]]);
    }
    header("Location: trending.php");
    exit;
}

$posts = $db->prepare("
SELECT posts.*, users.username,
       (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) +
       (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) as engagement
FROM posts
JOIN users ON users.id = posts.user_id
ORDER BY engagement DESC, posts.created_at DESC
LIMIT 20
");
$posts->execute();
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social - trending</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Social</h1>
            <?php include 'navbar.php'; ?>
        </header>

        <h2>Trending Posts</h2>

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

        <div class="post" id="post-<?=$postId?>">
            <div class="username">@<?=htmlspecialchars($p["username"])?></div>
            <div class="content"><?=htmlspecialchars($p["content"])?></div>
            <div class="post-actions">
                <button type="button" onclick="likePost(<?=$postId?>, this)" class="like-btn <?=$isLiked ? 'liked' : ''?>">
                    Like
                </button>
                <span class="like-count">(<?=$likeCount?>)</span>
                <button class="comment-btn" onclick="toggleComments(<?=$postId?>)">Comment</button>
            </div>
            <div id="forward-<?=$postId?>" style="display: none;">
                <form method="post" class="forward-form" action="messages.php">
                    <input type="hidden" name="csrf_token" value="<?=generateCSRFToken()?>">
                    <input type="hidden" name="post_id" value="<?=$postId?>">
                    <select name="receiver" required>
                        <option value="">Select recipient</option>
                        <?php
                        $connections = $db->prepare("
                        SELECT DISTINCT u.username, u.id
                        FROM users u
                        JOIN follows f ON (f.follower_id = u.id AND f.following_id = ?) OR (f.following_id = u.id AND f.follower_id = ?)
                        WHERE u.id != ?
                        ORDER BY u.username
                        ");
                        $connections->execute([$userId, $userId, $userId]);
                        foreach ($connections as $conn): ?>
                        <option value="<?=$conn['id']?>">@<?=htmlspecialchars($conn['username'])?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Send</button>
                </form>
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

        <a href="index.php" class="back-link">Back to Home</a>
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