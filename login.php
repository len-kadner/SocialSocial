<?php
require "db.php";
session_start();

if ($_POST && isset($_POST["email"])) {
    $stmt = $db->prepare("SELECT u.* FROM users u JOIN emails e ON u.id = e.id WHERE e.email = ?");
    $stmt->execute([$_POST["email"]]);
    $u = $stmt->fetch();

    if ($u && password_verify($_POST["password"], $u["password"])) {
        $_SESSION["user_id"] = $u["id"];
        header("Location: index.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social - login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <form method="post" class="login-form">
            <h2>Login to Social</h2>
            <input name="email" placeholder="Email" required>
            <input name="password" type="password" placeholder="Password" required>
            <button>Login</button>
            <p>Don't have an Account? <a href="register.php">Register here</a></p>
        </form>
    </div>
</body>
</html>
