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

// Get followers and following (mutual connections)
$connections = $db->prepare("
SELECT DISTINCT u.username, u.id
FROM users u
JOIN follows f ON (f.follower_id = u.id AND f.following_id = ?) OR (f.following_id = u.id AND f.follower_id = ?)
WHERE u.id != ?
ORDER BY u.username
");
$connections->execute([$userId, $userId, $userId]);

// Filter by search query if provided
$searchQuery = $_GET["search"] ?? "";
$filteredConnections = [];
foreach ($connections as $conn) {
    if (empty($searchQuery) || stripos($conn["username"], $searchQuery) !== false) {
        $filteredConnections[] = $conn;
    }
}

$selectedUser = $_GET["user"] ?? null;
$messages = [];
if ($selectedUser) {
    // Check if selected user is a connection
    $isConnection = false;
    foreach ($filteredConnections as $c) {
        if ($c["id"] == $selectedUser) {
            $isConnection = true;
            break;
        }
    }
    if (!$isConnection) {
        $selectedUser = null;
    } else {
        $messages = $db->prepare("
        SELECT m.*, u.username as sender_name
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
        ");
        $messages->execute([$userId, $selectedUser, $selectedUser, $userId]);
    }
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
                <h3>Chat with Connections</h3>
                <form method="get" class="search-form">
                    <input type="text" name="search" placeholder="Search followers..." value="<?=htmlspecialchars($searchQuery)?>">
                    <button type="submit">Search</button>
                </form>
                <?php foreach ($filteredConnections as $conn): ?>
                <a href="?user=<?=$conn["id"]?>&search=<?=urlencode($searchQuery)?>" class="conversation-item <?=$selectedUser == $conn["id"] ? 'active' : ''?>">
                    @<?=htmlspecialchars($conn["username"])?>
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