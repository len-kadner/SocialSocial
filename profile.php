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

if (isset($_POST["email"])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        die('CSRF token mismatch');
    }
    $newEmail = trim($_POST["email"]);
    $db->prepare("INSERT OR REPLACE INTO emails (id, email) VALUES (?, ?)")->execute([$profileId, $newEmail]);
    header("Location: profile.php?id=$profileId");
    exit;
}

if (isset($_POST["delete_post"])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        die('CSRF token mismatch');
    }
    $postId = $_POST["delete_post"];
    $stmt = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    if ($post && $post["user_id"] == $_SESSION["user_id"]) {
        $db->prepare("DELETE FROM posts WHERE id = ?")->execute([$postId]);
        $db->prepare("DELETE FROM comments WHERE post_id = ?")->execute([$postId]);
        $db->prepare("DELETE FROM likes WHERE post_id = ?")->execute([$postId]);
    }
    header("Location: profile.php?id=$profileId");
    exit;
}

if (isset($_POST["delete_account"])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        die('CSRF token mismatch');
    }
    $userId = $_SESSION["user_id"];
    // Delete all user data
    $db->prepare("DELETE FROM posts WHERE user_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM comments WHERE user_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM likes WHERE user_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM follows WHERE follower_id = ? OR following_id = ?")->execute([$userId, $userId]);
    $db->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")->execute([$userId, $userId]);
    $db->prepare("DELETE FROM emails WHERE id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
    session_destroy();
    header("Location: login.php");
    exit;
}
$email = $db->prepare("SELECT email FROM emails WHERE id=?");
$email->execute([$profileId]);
$email = $email->fetch();

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

/*
$stmt = $db->prepare("SQL MIT ?");
$stmt->execute([$wert]);
$result = $stmt->fetch();
*/
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
            <?php include 'navbar.php'; ?>
        </header>

        <div class="profile-header">
            <h2>@<?=$user["username"]?></h2>
            <div style="margin-top: 10px; color: #666666;">
                <span>Followers: <?=$followerCount?></span> | <span>Following: <?=$followingCount?></span> |
                <span>Contact: <?=$email ? htmlspecialchars($email["email"]) : "No email set"?></span>
            </div>
        </div>

        <?php if ($profileId == $_SESSION["user_id"] && !$email): ?>
        <form method="post" style="text-align: center; margin-bottom: 20px;">
            <input type="hidden" name="csrf_token" value="<?=generateCSRFToken()?>">
            <label for="email" style="display: block; margin-bottom: 10px;">E-Mail-Adresse hinzufügen:</label>
            <input type="email" name="email" id="email" required style="padding: 8px; border: 1px solid #e0e0e0; border-radius: 5px;">
            <button type="submit" style="padding: 8px 16px; background-color: #000000; color: #ffffff; border: none; border-radius: 5px; cursor: pointer;">Speichern</button>
        </form>
        <?php endif; ?>

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
                <?php if ($profileId == $_SESSION["user_id"]): ?>
                <button type="button" onclick="deletePost(<?=$postId?>)" class="delete-btn">Delete</button>
                <?php endif; ?>
            </div>
            <div id="comments-<?=$postId?>" style="display: none;">
                <form method="post" class="comment-form" action="index.php">
                    <input type="hidden" name="csrf_token" value="<?=generateCSRFToken()?>">
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

        <?php if ($profileId == $_SESSION["user_id"]): ?>
        <form method="post" style="text-align: center; margin-top: 30px; border-top: 1px solid #e0e0e0; padding-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?=generateCSRFToken()?>">
            <button type="button" onclick="deleteAccount()" style="padding: 10px 20px; background-color: #ff0000; color: #ffffff; border: none; border-radius: 5px; cursor: pointer;">Delete Account</button>
        </form>
        <?php endif; ?>

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

        function deletePost(postId) {
            if (confirm('Are you sure you want to delete this post?')) {
                const formData = new FormData();
                formData.append('delete_post', postId);
                formData.append('csrf_token', '<?=generateCSRFToken()?>');

                fetch('profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(() => location.reload());
            }
        }

        function deleteAccount() {
            if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('delete_account', '1');
                formData.append('csrf_token', '<?=generateCSRFToken()?>');

                fetch('profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(() => window.location.href = 'login.php');
            }
        }
    </script>
</body>
</html>
