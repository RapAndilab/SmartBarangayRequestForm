<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$username = $_SESSION['username'] ?? 'Guest';
$user_id = $_SESSION['user_id'] ?? 0;

$profile_src = "assets/default-profile.jpg";
if ($user_id > 0) {
    $url = "http://127.0.0.1:8000/api/get_profile_image/?user_id={$user_id}";
    
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "Cookie: " . $_SERVER['HTTP_COOKIE'] . "\r\n"
        ]
    ];
    $context = stream_context_create($opts);

    $json = @file_get_contents($url, false, $context);

    if ($json !== false) {
        $data = json_decode($json, true);
        if (!empty($data['image_url'])) {
            $profile_src = "http://127.0.0.1:8000" . $data['image_url'];
        }
        else {
            $profile_src = "profile_pics/default-avatar.png";
        }
    }
}
?>
<style>
    .header {
        width: 100%;
        background-color: #0d3b66;
        color: white;
        padding: 30px 0;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .header-content {
        max-width: 1500px;
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .header-icons {
        display: flex;
        gap: 30px;
        align-items: center;
        position: relative;
    }

    .header-icons i {
        font-size: 22px;
        cursor: pointer;
        transition: transform 0.2s ease;
        position: relative;
    }

    .header-icons i:hover {
        transform: scale(1.2);
    }

    .dropdown {
        font-size: 15px;
        color: #0d3b66;
        position: absolute;
        top: 60px;
        right: 0;
        background-color: white;
        color: black;
        border-radius: 8px;
        box-shadow: 0 5px 10px rgba(0,0,0,0.2);
        display: none;
        min-width: 200px;
        z-index: 100;
        padding: 15px;
        text-align: center;
    }

    .dropdown.active {
        display: block;
    }

    .dropdown img.profile-pic {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        margin-bottom: 10px;
        border: 2px solid #0d3b66;
    }

    .dropdown p {
        margin: 8px 0;
        font-size: 18px;
    }

    a.home-link {
        color: white;
        text-decoration: none;
        font-size: 18px;
    }

    .message-dropdown,
    .notification-dropdown {
        text-align: left;
        padding: 10px;
    }

    .message-dropdown p,
    .notification-dropdown p {
        margin: 0;
        padding: 8px;
        border-bottom: 1px solid #ddd;
        font-size: 14px;
    }

    .message-dropdown p:last-child,
    .notification-dropdown p:last-child {
        border-bottom: none;
    }
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div class="header">
    <div class="header-content">
        <a class="home-link" href="../home.php">🏠 HOME</a>

        <div class="header-icons">
            <!-- Envelope Dropdown -->
            <div class="dropdown-container">
                <i class="fas fa-envelope" title="Messages" onclick="toggleDropdown('messageDropdown')"></i>
                <div id="messageDropdown" class="dropdown message-dropdown">
                    <p>No new messages</p>
                </div>
            </div>

            <!-- Bell Dropdown -->
            <div class="dropdown-container">
                <i class="fas fa-bell" title="Notifications" onclick="toggleDropdown('notificationDropdown')"></i>
                <div id="notificationDropdown" class="dropdown notification-dropdown">
                    <p>No new notifications</p>
                </div>
            </div>

            <!-- User Menu Dropdown -->
            <div class="dropdown-container">
                <i class="fas fa-bars" title="User Menu" onclick="toggleDropdown('userDropdown')"></i>
                <div id="userDropdown" class="dropdown">
                    <img src="<?= htmlspecialchars($profile_src) ?>" class="profile-pic" alt="Profile Picture">
                    <p><strong><?= htmlspecialchars($username) ?></strong></p>
                    <p><a href="../logout.php" style="color: #0d3b66; text-decoration: none;">Logout</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleDropdown(id) {
        // Hide other dropdowns
        document.querySelectorAll('.dropdown').forEach(el => {
            if (el.id !== id) el.classList.remove('active');
        });

        // Toggle the selected one
        const target = document.getElementById(id);
        if (target) {
            target.classList.toggle('active');
        }
    }

    // Close all dropdowns if clicked outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-container')) {
            document.querySelectorAll('.dropdown').forEach(el => el.classList.remove('active'));
        }
    });
</script>
