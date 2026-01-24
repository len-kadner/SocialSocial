<?php
require "db.php";
require "auth.php";

$userId = $_SESSION["user_id"];

$searchQuery = $_GET["search"] ?? "";  

$searchResults = []; 
if (isset($_GET["search"]) && !empty(trim($_GET["search"]))) {
    $query = "%" . trim($_GET["search"]) . "%";  // LIKE-Suche
    $stmt = $db->prepare("SELECT id, username FROM users WHERE username LIKE ? ORDER BY username");
    $stmt->execute([$query]);
    $searchResults = $stmt->fetchAll();  
}

$selectedUser = $_GET["user"] ?? null;  
$messages = []; 
if ($selectedUser) {

    $stmt = $db->prepare("
        SELECT m.*, u.username as sender_name
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
    ");
    $stmt->execute([$userId, $selectedUser, $selectedUser, $userId]);
    $messages = $stmt->fetchAll();
}

//(POST-Handling)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["message"])) {
    $content = trim($_POST["message"]);
    if (!empty($content) && $selectedUser) {
        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $selectedUser, $content]);

        header("Location: messages.php?user=" . $selectedUser . "&search=" . urlencode($searchQuery));
        exit;
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
            <?php include 'navbar.php'; ?>
        </header>
<div class="messages-container">
    <div class="conversations">
        <form method="get" class="search-form">
            <input type="text" name="search" placeholder="Search..." value="<?=htmlspecialchars($searchQuery)?>">
            <button type="submit">Search</button>
        </form>
        <div class="searchresult">
            <?php if (!empty($searchResults)): ?>
                <div class="user-list">
                    <h3>Search Results</h3>
                    <?php foreach ($searchResults as $user): ?>
                        <div class="user-item">
                            <span class="username">@<?=htmlspecialchars($user["username"])?></span>
                            <a href="?user=<?=$user["id"]?>&search=<?=urlencode($searchQuery)?>">Chat</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (isset($_GET["search"])): ?>
                <p>No users found.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="chat">
        <?php if ($selectedUser): ?>
            <h3>Chat with @<?=htmlspecialchars($searchResults[array_search($selectedUser, array_column($searchResults, 'id'))]['username'] ?? 'User')?></h3>
            <div class="chat-messages">
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?=$msg["sender_id"] == $userId ? 'sent' : 'received'?>">
                        <strong>@<?=htmlspecialchars($msg["sender_name"])?>:</strong> <?=htmlspecialchars($msg["content"])?>
                        <small><?=date('H:i d.m.Y', strtotime($msg["created_at"]))?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="post" class="message-form">
                <input name="message" placeholder="Type a message..." required>
                <button type="submit">Send</button>
            </form>
        <?php else: ?>
            <p>Select a user to start chatting.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>