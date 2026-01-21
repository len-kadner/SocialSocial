<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$navItems = [
    'index.php' => 'Home',
    'trending.php' => 'Trending',
    'search.php' => 'Search',
    'messages.php' => 'Messages',
    'profile.php' => 'My Profile'
];
?>
<nav class="nav-links">
    <?php foreach ($navItems as $page => $label): ?>
        <a href="<?=$page?>" class="<?=$currentPage == $page ? 'active' : ''?>"><?=$label?></a>
    <?php endforeach; ?>
    <a href="logout.php">Logout</a>
</nav>