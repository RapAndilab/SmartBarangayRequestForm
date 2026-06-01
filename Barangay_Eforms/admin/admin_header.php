<?php
session_start();

$admin_name = $_SESSION['username'] ?? 'Admin';
$current_page = basename($_SERVER['PHP_SELF']);
$show_home_button = $current_page !== 'admin_dashboard.php';
?>

<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
    .admin-header {
        width: 100%;
        background-color: rgb(0, 0, 0);
        color: white;
        padding: 30px 0;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .admin-header-content {
        max-width: 1500px;
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .admin-title {
        font-size: 22px;
        font-weight: bold;
    }

    .admin-title a {
        color: white;
        text-decoration: none;
    }

    .admin-title a:hover {
        text-decoration: underline;
    }

    .admin-icons {
        display: flex;
        gap: 20px;
        align-items: center;
        position: relative;
    }

    .admin-icons i {
        font-size: 22px;
        cursor: pointer;
        transition: transform 0.2s ease;
    }

    .admin-icons i:hover {
        transform: scale(1.2);
    }

    .admin-dropdown,
    .message-dropdown {
        position: absolute;
        top: 40px;
        right: 0;
        background-color: white;
        color: black;
        border-radius: 8px;
        box-shadow: 0 5px 10px rgba(0,0,0,0.2);
        display: none;
        min-width: 220px;
        z-index: 100;
        padding: 15px;
        text-align: center;
    }

    .admin-dropdown.active,
    .message-dropdown.active {
        display: block;
    }

    .admin-dropdown p {
        margin: 8px 0;
        font-size: 15px;
    }

    .admin-dropdown a {
        color: #0d3b66;
        text-decoration: none;
        font-weight: bold;
        display: inline-block;
        margin-top: 10px;
    }

    .admin-dropdown a:hover {
        text-decoration: underline;
    }

    .message-dropdown ul {
        list-style: none;
        padding: 0;
        margin: 0;
        text-align: left;
        max-height: 200px;
        overflow-y: auto;
    }

    .message-dropdown li {
        padding: 8px;
        border-bottom: 1px solid #ddd;
        font-size: 14px;
    }
</style>

<div class="admin-header">
    <div class="admin-header-content">
        <div class="admin-title">
            <?php if ($show_home_button): ?>
                <a href="admin_dashboard.php"><i class="fas fa-arrow-left"></i> Home</a>
            <?php else: ?>
                📊 Admin Dashboard
            <?php endif; ?>
        </div>

        <div class="admin-icons">
            <i class="fas fa-envelope" title="Messages" onclick="toggleMessageDropdown()"></i>
            <div id="messageDropdown" class="message-dropdown">
                <strong>Notifications</strong>
                <ul>
                    <li>No new messages</li>
                    <!-- Dynamically add <li> entries here if needed -->
                </ul>
            </div>

            <i class="fas fa-bars" title="Admin Menu" onclick="toggleAdminDropdown()"></i>
            <div id="adminDropdown" class="admin-dropdown">
                <p><strong><?= htmlspecialchars($admin_name) ?></strong></p>
                <a href="admin_logout.php">Logout</a>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleAdminDropdown() {
        document.getElementById('adminDropdown').classList.toggle('active');
        document.getElementById('messageDropdown').classList.remove('active');
    }

    function toggleMessageDropdown() {
        document.getElementById('messageDropdown').classList.toggle('active');
        document.getElementById('adminDropdown').classList.remove('active');
    }

    document.addEventListener('click', function(e) {
        const adminDrop = document.getElementById('adminDropdown');
        const msgDrop = document.getElementById('messageDropdown');
        const bars = document.querySelector('.fa-bars');
        const mail = document.querySelector('.fa-envelope');
        if (!adminDrop.contains(e.target) && !bars.contains(e.target)) {
            adminDrop.classList.remove('active');
        }
        if (!msgDrop.contains(e.target) && !mail.contains(e.target)) {
            msgDrop.classList.remove('active');
        }
    });
</script>
