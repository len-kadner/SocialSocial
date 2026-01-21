<?php
require "db.php";
require "auth.php";

$searchResults = [];
if (isset($_GET["q"]) && !empty(trim($_GET["q"]))) {
    $query = "%" . trim($_GET["q"]) . "%";
    $stmt = $db->prepare("SELECT id, username FROM users WHERE username LIKE ? ORDER BY username");
    $stmt->execute([$query]);
    $searchResults = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social - Search</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Social</h1>
            <?php include 'navbar.php'; ?>
        </header>

        <div class="search-bar">
            <form method="get">
                <input name="q" placeholder="Search users..." value="<?=htmlspecialchars($_GET["q"] ?? "")?>" required>
            </form>
        </div>

        <?php if (!empty($searchResults)): ?>
        <div class="user-list">
            <h3>Search Results</h3>
            <?php foreach ($searchResults as $user): ?>
            <div class="user-item">
                <span class="username">@<?=htmlspecialchars($user["username"])?></span>
                <a href="profile.php?id=<?=$user["id"]?>">View Profile</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php elseif (isset($_GET["q"])): ?>
        <p>No users found matching "<?=htmlspecialchars($_GET["q"])?>".</p>
        <?php endif; ?>

        <a href="index.php" class="back-link">Back to Home</a>
    </div>
</body>
</html>