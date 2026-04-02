<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">

    <div class="logo">VPM Admin</div>

    <nav>

        <a href="dashboard.php" 
           class="nav-item <?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>">
            Dashboard
        </a>

        <a href="vehicles.php" 
           class="nav-item <?php echo ($currentPage == 'vehicles.php') ? 'active' : ''; ?>">
            Vehicles
        </a>

        <a href="users.php" 
           class="nav-item <?php echo ($currentPage == 'users.php') ? 'active' : ''; ?>">
            Users
        </a>

        <a href="settings.php" 
           class="nav-item <?php echo ($currentPage == 'settings.php') ? 'active' : ''; ?>">
            Settings
        </a>

    </nav>

</aside>
