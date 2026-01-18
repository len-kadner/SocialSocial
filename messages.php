<?php
require "db.php";
require "auth.php";

$userId = $_SESSION["user_id"];

if ($_POST["message"] ?? false) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        die('CSRF token mismatch');
    }
    $receiver = trim($_POST["receiver"]);
    $content = trim($_POST["message"]);

    // Get receiver ID
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$receiver]);
    $rec = $stmt->fetch();
    if ($rec) {
        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $rec["id"], $content]);
    }
    header("Location: messages.php");
    exit;
}

// Get conversations (users who sent or received messages)
$conversations = $db->prepare("
SELECT DISTINCT u.username, u.id,
       (SELECT content FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_message,
       (SELECT created_at FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_time
FROM users u
JOIN messages m ON (m.sender_id = u.id OR m.receiver_id = u.id)
WHERE u.id != ?
ORDER BY last_time DESC
");
$conversations->execute([$userId, $userId, $userId, $userId, $userId]);

$selectedUser = $_GET["user"] ?? null;
$messages = [];
if ($selectedUser) {
    $messages = $db->prepare("
    SELECT m.*, u.username as sender_name
    FROM messages m
    JOIN users u ON u.id = m.sender_id
    WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
    ORDER BY created_at ASC
    ");
    $messages->execute([$userId, $selectedUser, $selectedUser, $userId]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social - Messages</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Social</h1>
            <nav class="nav-links">
                <a href="index.php">Home</a>
                <a href="profile.php">My Profile</a>
                <a href="search.php">Search</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <div class="messages-container">
            <div class="conversations">
                <h3>Conversations</h3>
                <?php foreach ($conversations as $conv): ?>
                <a href="?user=<?=$conv["id"]?>" class="conversation-item <?=$selectedUser == $conv["id"] ? 'active' : ''?>">
                    @<?=htmlspecialchars($conv["username"])?>
                    <div class="last-message"><?=htmlspecialchars(substr($conv["last_message"], 0, 50))?>...</div>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="chat">
                <?php if ($selectedUser): ?>
                <div class="chat-messages">
                    <?php foreach ($messages as $msg): ?>
                    <div class="message <?=$msg["sender_id"] == $userId ? 'sent' : 'received'?>">
                        <strong>@<?=htmlspecialchars($msg["sender_name"])?>:</strong> <?=htmlspecialchars($msg["content"])?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <form method="post" class="message-form">
                    <input type="hidden" name="csrf_token" value="<?=generateCSRFToken()?>">
                    <input type="hidden" name="receiver" value="<?=$selectedUser?>">
                    <input name="message" placeholder="Type a message..." required>
                    <button>Send</button>
                </form>
                <?php else: ?>
                <p>Select a conversation to start chatting.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>