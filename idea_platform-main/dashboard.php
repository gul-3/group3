<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php'; // Veritabanı bağlantısı

$project_ideas = [];
$total_projects = 0;
$database_error_message = ''; // Veritabanı hata mesajını tutacak değişken

// Veritabanı bağlantısının var olup olmadığını ve canlı olup olmadığını kontrol et
if (isset($conn) && $conn->ping()) {
    $sql = "SELECT 
                pi.id AS project_id, 
                pi.project_title, 
                pi.image_filename AS project_image, 
                pi.created_at, 
                u.fullname AS creator_fullname, 
                u.department AS creator_department, 
                u.profile_image_filename AS creator_profile_pic 
            FROM 
                project_ideas pi 
            LEFT JOIN 
                users u ON pi.creator_user_id = u.id 
            ORDER BY 
                pi.created_at DESC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $project_ideas = $result->fetch_all(MYSQLI_ASSOC);
        $total_projects = $result->num_rows;
        $result->free();
    } else {
        // SQL sorgu hatasını yakala
        $database_error_message = "Proje fikirleri çekilirken hata oluştu: " . $conn->error;
        error_log($database_error_message); // Sunucu hata günlüğüne yaz
    }
} else {
    // Veritabanı bağlantı sorununu belirle
    $database_error_message = "dashboard.php içinde veritabanı bağlantısı kurulamadı veya kayboldu."; // Updated message
    if (isset($conn) && !$conn->ping()) {
         $database_error_message .= " Ping başarısız. MySQL sunucusu gitmiş veya bağlantı zaman aşımına uğramış olabilir.";
    } elseif (!isset($conn)) {
         $database_error_message .= " $conn değişkeni ayarlanmamış.";
    }
    error_log($database_error_message);
}

// Bu değişken tanımlamaları DEBUG bloğundan sonra da kalabilir, sorun değil.
$user_profile_pic_path = 'uploads/profile_pics/';
$project_image_path = 'uploads/project_images/';
$default_user_pic = 'images/pp_placeholder.png';
$default_project_pic = 'images/project_img_placeholder.png'; 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f0f2f5; /* Light grey background like the image */
            color: #333;
        }

        #app-header {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background-color: #fff; 
            border-bottom: 1px solid #e0e0e0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000; /* Ensure header is on top */
        }

        .logo-image {
            height: 40px; 
            display: block; 
        }

        #hamburger-icon {
            font-size: 24px;
            cursor: pointer;
            margin-left: 20px;
            color: #555;
        }

        #sidebar {
            height: calc(100vh - 70px); 
            width: 60px; 
            position: fixed;
            top: 70px; 
            left: -60px; 
            background-color: #ffffff; 
            border-right: 1px solid #e0e0e0;
            transition: left 0.3s ease;
            z-index: 999; 
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }

        #sidebar.open {
            left: 0;
        }
       
        #sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        #sidebar li a {
            display: flex;
            align-items: center;
            justify-content: center; 
            padding: 15px 10px; 
            color: #555; 
            text-decoration: none;
            border-left: 3px solid transparent; 
        }
        
        #sidebar li .icon {
            margin-right: 0; 
            width: 24px; 
            height: 24px; 
        }

        #sidebar li a:hover {
            background-color: #f0f0f0; 
        }

        #sidebar li.active a {
            color: #007bff; 
            font-weight: bold;
            border-left-color: #007bff; 
            background-color: #e7f3ff; 
        }

        #main-content {
            padding: 20px;
            margin-top: 70px; 
            margin-left: 80px; 
            transition: margin-left 0.3s ease; 
        }

        #main-content .dashboard-header h1 {
            color: #1c3d5a; /* Dark blue like image */
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 5px; /* Space between header and count */
        }
        .total-projects-count {
            font-size: 1.2em;
            color: #555;
            margin-bottom: 25px;
            padding-left: 5px; /* Align with header text */
        }
        .total-projects-count span {
            font-weight: bold;
            color: #333;
        }

        .project-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); /* Responsive 3-columnish */
            gap: 20px;
        }

        .project-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden; /* To contain image */
            display: flex;
            flex-direction: column;
        }

        .project-card .card-user-info {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }

        .project-card .user-avatar img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }

        .project-card .user-details .user-name {
            font-weight: bold;
            color: #333;
            font-size: 0.9em;
        }

        .project-card .user-details .user-department {
            font-size: 0.8em;
            color: #777;
        }

        .project-card .project-image-container {
            width: 100%;
            padding-top: 56.25%; /* 16:9 Aspect Ratio */
            position: relative;
            background-color: #e9ecef; /* Placeholder background */
        }

        .project-card .project-image-container img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .project-card .project-image-container .placeholder-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 3em; /* Adjust as needed */
            color: #adb5bd;
        }


        .project-card .card-project-title {
            padding: 10px 15px;
            font-weight: bold;
            color: #333;
            font-size: 1.1em;
            border-top: 1px solid #eee;
        }

        .logout-link {
            margin-left: auto; 
            padding: 0 10px; 
            display: flex; 
            align-items: center;
        }
        .logout-link img {
            height: 24px; 
            width: 24px;  
        }

    </style>
</head>
<body>
    <div id="app-header">
        <img src="images/logo.png" alt="App Logo" class="logo-image">
        <span id="hamburger-icon">&#9776;</span>
        <a href="logout.php" class="logout-link"><img src="images/logout_icon.png" alt="Logout" title="Logout"></a>
    </div>

    <nav id="sidebar">
        <ul>
            <li class="active"><a href="dashboard.php" title="Dashboard"><img src="images/dashboard_icon.png" alt="Dashboard" class="icon" style="width:24px; height:24px;"></a></li>
            <li><a href="new_project_idea.php" title="New Project Idea"><img src="images/new_idea.png" alt="New Project Idea" class="icon" style="width:24px; height:24px;"></a></li>
            <li><a href="edit_profile.php" title="Profile"><img src="images/profile_icon.png" alt="Profile" class="icon" style="width:24px; height:24px;"></a></li>
        </ul>
    </nav>

    <main id="main-content">
        <div class="dashboard-header">
            <h1>Dashboard</h1>
            <div class="total-projects-count">
                Last project ideas: <span><?php echo $total_projects; ?></span>
            </div>
        </div>
        
        <div class="project-gallery">
            <?php if (!empty($project_ideas)): ?>
                <?php foreach ($project_ideas as $idea): ?>
                    <div class="project-card">
                        <div class="card-user-info">
                            <div class="user-avatar">
                                <img src="<?php echo !empty($idea['creator_profile_pic']) ? $user_profile_pic_path . htmlspecialchars($idea['creator_profile_pic']) : $default_user_pic; ?>" alt="<?php echo htmlspecialchars($idea['creator_fullname']); ?>">
                            </div>
                            <div class="user-details">
                                <div class="user-name"><?php echo htmlspecialchars($idea['creator_fullname'] ?? 'Unknown User'); ?></div>
                                <div class="user-department"><?php echo htmlspecialchars($idea['creator_department'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <div class="project-image-container">
                            <img src="<?php echo !empty($idea['project_image']) ? $project_image_path . htmlspecialchars($idea['project_image']) : $default_project_pic; ?>" alt="<?php echo htmlspecialchars($idea['project_title']); ?>">
                        </div>
                        <div class="card-project-title">
                            <?php echo htmlspecialchars($idea['project_title']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No project ideas found yet. Be the first to <a href="new_project_idea.php">add one</a>!</p>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const hamburger = document.getElementById('hamburger-icon');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const sidebarWidth = 60; 
        const mainContentMarginClosed = '80px'; 

        hamburger.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            if (sidebar.classList.contains('open')) {
                mainContent.style.marginLeft = sidebarWidth + 'px';
            } else {
                mainContent.style.marginLeft = mainContentMarginClosed;
            }
        });

        mainContent.addEventListener('click', () => {
            if (sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                mainContent.style.marginLeft = mainContentMarginClosed;
            }
        });
    </script>
</body>
</html> 