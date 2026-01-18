<?php
require "db.php";
require "auth.php";

$profileId = $_GET["id"] ?? $_SESSION["user_id"];

if (isset($_POST["follow"])) {
    $existing = $db->prepare("SELECT * FROM follows WHERE follower_id = ? AND following_id = ?");
    $existing->execute([$_SESSION["user_id"], $profileId]);
    if ($existing->fetch()) {
        $db->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?")->execute([$_SESSION["user_id"], $profileId]);
    } else {
        $db->prepare("INSERT INTO follows (follower_id, following_id) VALUES (?, ?)")->execute([$_SESSION["user_id"], $profileId]);
    }
    header("Location: profile.php?id=$profileId");
    exit;
}

$user = $db->prepare("SELECT username FROM users WHERE id=?");
$user->execute([$profileId]);
$user = $user->fetch();

$followers = $db->prepare("SELECT COUNT(*) as count FROM follows WHERE following_id = ?");
$followers->execute([$profileId]);
$followerCount = $followers->fetch()["count"];

$following = $db->prepare("SELECT COUNT(*) as count FROM follows WHERE follower_id = ?");
$following->execute([$profileId]);
$followingCount = $following->fetch()["count"];

$isFollowing = false;
if ($profileId != $_SESSION["user_id"]) {
    $check = $db->prepare("SELECT * FROM follows WHERE follower_id = ? AND following_id = ?");
    $check->execute([$_SESSION["user_id"], $profileId]);
    $isFollowing = $check->fetch();
}

$posts = $db->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC");
$posts->execute([$profileId]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social - Profile</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Social</h1>
            <nav class="nav-links">
                <a href="index.php">Home</a>                <a href="search.php">Search</a>                <a href="messages.php">Messages</a>                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <div class="profile-header">
            <h2>@<?=$user["username"]?></h2>
            <div style="margin-top: 10px; color: #666666;">
                <span>Followers: <?=$followerCount?></span> | <span>Following: <?=$followingCount?></span>
            </div>
        </div>

        <?php if ($profileId != $_SESSION["user_id"]): ?>
        <form method="post" style="text-align: center;">
            <button name="follow" class="follow-btn"><?=$isFollowing ? 'Unfollow' : 'Follow'?></button>
        </form>
        <?php endif; ?>

        <h3 style="color: #000000; margin-top: 30px;">Posts</h3>
        <?php foreach ($posts as $p): ?>
        <?php
        $postId = $p["id"];
        $likes = $db->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
        $likes->execute([$postId]);
        $likeCount = $likes->fetch()["count"];

        $userLiked = $db->prepare("SELECT * FROM likes WHERE user_id = ? AND post_id = ?");
        $userLiked->execute([$_SESSION["user_id"], $postId]);
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
            <div class="username">@<?=htmlspecialchars($user["username"])?></div>
            <div class="content"><?=htmlspecialchars($p["content"])?></div>
            <div class="post-actions">
                <button type="button" onclick="likePost(<?=$postId?>, this)" class="like-btn <?=$isLiked ? 'liked' : ''?>">
                    Like
                </button>
                <span class="like-count">(<?=$likeCount?>)</span>
                <button class="comment-btn" onclick="toggleComments(<?=$postId?>)">Comment</button>
            </div>
            <div id="comments-<?=$postId?>" style="display: none;">
                <form method="post" class="comment-form" action="index.php">
                    <input name="comment" placeholder="Write a comment..." required>
                    <input type="hidden" name="post_id" value="<?=$postId?>">
                    <button>Comment</button>
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
