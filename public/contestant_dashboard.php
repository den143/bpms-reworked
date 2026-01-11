<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Contestant');
require_once __DIR__ . '/../app/config/database.php';

$user_id = $_SESSION['user_id'];
$message = "";

// 1. HANDLE PROFILE UPDATES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_bio') {
    $new_motto = trim($_POST['motto']);
    $new_hometown = trim($_POST['hometown']);
    
    // A. HANDLE PHOTO UPLOAD
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        $file_name = $_FILES['photo']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (in_array($file_ext, $allowed_ext)) {
            // Generate unique name: contestant_ID_TIMESTAMP.ext
            $new_filename = "contestant_" . $user_id . "_" . time() . "." . $file_ext;
            $upload_dir = __DIR__ . '/assets/uploads/contestants/';
            
            // Create directory if missing
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_filename)) {
                // Update Photo in DB
                $photo_stmt = $conn->prepare("UPDATE contestant_details SET photo = ? WHERE user_id = ?");
                $photo_stmt->bind_param("si", $new_filename, $user_id);
                $photo_stmt->execute();
            }
        }
    }

    // B. UPDATE TEXT DETAILS
    $upd = $conn->prepare("UPDATE contestant_details SET motto = ?, hometown = ? WHERE user_id = ?");
    $upd->bind_param("ssi", $new_motto, $new_hometown, $user_id);
    if ($upd->execute()) {
        $message = "Profile updated successfully.";
    }
}

// 2. FETCH DATA
$sql = "SELECT u.name, u.email, u.status, 
               cd.age, cd.height, cd.vital_stats, cd.hometown, cd.motto, cd.photo, cd.contestant_number,
               e.id as event_id, e.name as event_name, e.event_date, e.venue, e.status as event_status
        FROM users u 
        LEFT JOIN contestant_details cd ON u.id = cd.user_id 
        LEFT JOIN events e ON cd.event_id = e.id 
        WHERE u.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

// 3. SECURITY CHECK
if ($data['status'] === 'Inactive' || $data['status'] === 'Rejected') {
    die("Access Restricted. Contact Administrator."); 
}

// 4. ACTIVITIES
$activities = [];
if ($data['event_id']) {
    $act_sql = "SELECT title, description, activity_date, start_time, venue 
                FROM activities 
                WHERE event_id = ? AND is_deleted = 0 
                ORDER BY activity_date ASC, start_time ASC";
    $act_stmt = $conn->prepare($act_sql);
    $act_stmt->bind_param("i", $data['event_id']);
    $act_stmt->execute();
    $res = $act_stmt->get_result();
    while ($row = $res->fetch_assoc()) $activities[] = $row;
}

// Countdown
$days_left = 0;
if ($data['event_date']) {
    $event_date = new DateTime($data['event_date']);
    $today = new DateTime();
    if ($event_date > $today) $days_left = $today->diff($event_date)->days;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Candidate Portal - <?= htmlspecialchars($data['event_name']) ?></title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- MOBILE BASE STYLES --- */
        :root { --slate-900: #0f172a; --slate-800: #1e293b; --gold: #F59E0B; }
        
        body { background-color: #f8fafc; overflow-x: hidden; }

        /* Navbar with Hamburger */
        .navbar {
            background: white; border-bottom: 1px solid #e2e8f0;
            padding: 15px 20px; position: sticky; top: 0; z-index: 50;
            display: flex; justify-content: space-between; align-items: center;
        }
        .nav-brand { font-weight: 800; color: var(--slate-900); font-size: 1.1rem; }
        .menu-toggle { display: none; font-size: 24px; color: var(--slate-900); cursor: pointer; border: none; background: none; }

        /* Mobile Sidebar Transformation */
        .sidebar { background-color: var(--slate-900); transition: transform 0.3s ease; }
        .sidebar-header { background-color: #020617; border-bottom: 1px solid var(--slate-800); }
        .sidebar-menu li a { color: #cbd5e1; }
        .sidebar-menu li a.active { background: rgba(255,255,255,0.1); color: var(--gold); border-left: 4px solid var(--gold); }

        /* Main Content Layout */
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .info-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }

        /* Cards & Widgets */
        .welcome-banner {
            background: linear-gradient(135deg, #F59E0B 0%, #B45309 100%);
            color: white; padding: 30px; border-radius: 16px; margin-bottom: 25px;
            position: relative; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(245, 158, 11, 0.2);
        }
        .candidate-number-badge {
            position: absolute; right: 20px; top: 50%; transform: translateY(-50%);
            font-size: 80px; font-weight: 900; opacity: 0.15; color: white;
            pointer-events: none;
        }

        .profile-card {
            background: white; border-radius: 16px; padding: 25px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); text-align: center;
            border: 1px solid #e2e8f0; position: sticky; top: 90px;
        }
        
        /* New Profile Image Styles */
        .profile-img-wrapper {
            position: relative; display: inline-block; margin-bottom: 15px;
        }
        .profile-img-wrapper img {
            width: 120px; height: 120px; border-radius: 50%; object-fit: cover;
            border: 4px solid #f8fafc; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .camera-btn {
            position: absolute; bottom: 5px; right: 5px;
            background: #0f172a; color: white; width: 32px; height: 32px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            cursor: pointer; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .camera-btn:hover { transform: scale(1.1); background: var(--gold); }

        /* Timeline / Schedule */
        .timeline { list-style: none; padding: 0; margin: 0; }
        .timeline-item {
            position: relative; padding-left: 35px; margin-bottom: 25px;
        }
        .timeline-item::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0;
            width: 2px; background: #e2e8f0;
        }
        .timeline-item::after {
            content: ''; position: absolute; left: -4px; top: 6px;
            width: 10px; height: 10px; border-radius: 50%; background: var(--gold);
            border: 2px solid white;
        }
        .timeline-date { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .timeline-card {
            background: white; padding: 15px; border-radius: 8px; margin-top: 5px;
            border: 1px solid #f1f5f9; box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        /* Mobile Overlay */
        .sidebar-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 900; backdrop-filter: blur(2px);
        }

        /* --- RESPONSIVE MEDIA QUERIES --- */
        @media (max-width: 900px) {
            .info-grid { grid-template-columns: 1fr; } /* Stack Grid */
            .profile-card { position: static; margin-bottom: 30px; order: -1; } /* Move Profile to Top */
        }

        @media (max-width: 768px) {
            /* Sidebar Logic */
            .main-wrapper { flex-direction: column; }
            .sidebar { 
                position: fixed; top: 0; left: 0; height: 100vh; width: 260px; z-index: 1000;
                transform: translateX(-100%); /* Hide by default */
            }
            .sidebar.active { transform: translateX(0); box-shadow: 5px 0 15px rgba(0,0,0,0.2); }
            .content-area { margin-left: 0; width: 100%; }
            .menu-toggle { display: block; }
            .sidebar-overlay.active { display: block; }

            /* Font & Spacing Adjustments */
            .container { padding: 15px; }
            .welcome-banner { padding: 20px; text-align: center; }
            .welcome-banner .flex-stats { justify-content: center; }
            .candidate-number-badge { font-size: 60px; right: 50%; transform: translate(50%, -50%); opacity: 0.1; }
            
            /* Better Form Touch Targets */
            button, input, textarea { font-size: 16px; } /* Prevents iOS zoom */
            .navbar-title { display: none; } /* Hide breadcrumb on mobile to save space */
        }
    </style>
</head>
<body>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="main-wrapper">
        
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="assets/images/BPMS_logo.png" alt="Logo" class="sidebar-logo">
                <div class="brand-text">
                    <div class="brand-name">BPMS</div>
                    <div class="brand-subtitle">Candidate Portal</div>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="contestant_dashboard.php" class="active"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            </ul>
            
            <div class="sidebar-footer">
                <a href="settings.php">
                    <i class="fas fa-cog"></i> <span>Settings</span>
                </a>
                <a href="logout.php" onclick="return confirm('Logout?');">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </div>
        </div>

        <div class="content-area">
            
            <div class="navbar">
                <div style="display:flex; align-items:center; gap:15px;">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="nav-brand">Candidate Portal</div>
                </div>
                <div style="font-size:14px; color:#64748b; font-weight:600;">
                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars(explode(' ', $data['name'])[0]) ?>
                </div>
            </div>

            <div class="container">
                
                <?php if ($message): ?>
                    <div class="toast success" style="margin-bottom: 20px; background: #10B981; color: white; padding: 15px; border-radius: 8px; font-weight:600;">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="welcome-banner">
                    <div style="position: relative; z-index: 2;">
                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:1px; opacity:0.8; margin-bottom:5px;">Road to the Crown</div>
                        <h2 style="margin:0; font-size:26px; line-height:1.2;"><?= htmlspecialchars($data['event_name']) ?></h2>
                        
                        <div class="flex-stats" style="margin-top: 25px; display: flex; gap: 30px;">
                            <div>
                                <div style="font-size: 24px; font-weight: 800;"><?= $days_left ?></div>
                                <div style="font-size: 11px; opacity: 0.9; text-transform:uppercase;">Days Left</div>
                            </div>
                            <div>
                                <div style="font-size: 24px; font-weight: 800;"><i class="fas fa-map-pin" style="font-size:18px;"></i></div>
                                <div style="font-size: 11px; opacity: 0.9; text-transform:uppercase;"><?= htmlspecialchars($data['venue']) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="candidate-number-badge">
                        #<?= $data['contestant_number'] ? str_pad($data['contestant_number'], 2, '0', STR_PAD_LEFT) : '?' ?>
                    </div>
                </div>

                <div class="info-grid">
                    
                    <div>
                        <h3 style="margin-top:0; color:#1e293b; font-size:18px; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
                            <span style="background:#e0f2fe; color:#0284c7; width:30px; height:30px; display:flex; align-items:center; justify-content:center; border-radius:8px; font-size:14px;"><i class="fas fa-calendar-alt"></i></span>
                            Schedule
                        </h3>

                        <?php if (empty($activities)): ?>
                            <div style="text-align:center; padding:30px; background:white; border-radius:12px; border:1px dashed #cbd5e1; color:#94a3b8;">
                                <i class="far fa-calendar-times" style="font-size:30px; margin-bottom:10px;"></i>
                                <p>No scheduled activities yet.</p>
                            </div>
                        <?php else: ?>
                            <ul class="timeline">
                                <?php foreach ($activities as $act): 
                                    $dateObj = new DateTime($act['activity_date']);
                                    $timeObj = new DateTime($act['start_time']);
                                    $isPast = $dateObj < new DateTime('today');
                                ?>
                                <li class="timeline-item" style="<?= $isPast ? 'opacity:0.6;' : '' ?>">
                                    <div class="timeline-date"><?= $dateObj->format('M d, Y') ?> • <?= $timeObj->format('g:i A') ?></div>
                                    <div class="timeline-card">
                                        <div style="font-weight: 700; color: #334155; margin-bottom:5px; font-size:15px;"><?= htmlspecialchars($act['title']) ?></div>
                                        <div style="font-size: 13px; color: #64748b; display:flex; align-items:center; gap:6px;">
                                            <i class="fas fa-map-marker-alt" style="color:#ef4444;"></i> <?= htmlspecialchars($act['venue']) ?>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div>
                        <form method="POST" enctype="multipart/form-data" class="profile-card">
                            <input type="hidden" name="action" value="update_bio">
                            
                            <div class="profile-img-wrapper">
                                <img id="profilePreview" src="./assets/uploads/contestants/<?= htmlspecialchars($data['photo']) ?>" 
                                     onerror="this.src='./assets/images/default_user.png'" alt="Profile">
                                
                                <label for="photoUpload" class="camera-btn" title="Change Photo">
                                    <i class="fas fa-camera"></i>
                                </label>
                                <input type="file" id="photoUpload" name="photo" accept="image/*" style="display:none;" onchange="previewImage(this)">
                            </div>
                            
                            <h3 style="margin:0; color:#0f172a; font-size:20px;"><?= htmlspecialchars($data['name']) ?></h3>
                            <div style="color:#d97706; font-weight:700; font-size:13px; margin-bottom:20px; text-transform:uppercase; letter-spacing:0.5px;">
                                <?= htmlspecialchars($data['hometown']) ?>
                            </div>

                            <div style="background:#f8fafc; border-radius:8px; padding:15px; margin-bottom:20px;">
                                <div style="display:flex; justify-content:space-between; font-size:14px; color:#475569; margin-bottom:8px;">
                                    <span>Age</span> <strong><?= $data['age'] ?></strong>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:14px; color:#475569;">
                                    <span>Height</span> <strong><?= htmlspecialchars($data['height']) ?></strong>
                                </div>
                            </div>

                            <div style="text-align: left; margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 700; color: #64748b; display:block; margin-bottom:4px;">Hometown</label>
                                <input type="text" name="hometown" class="form-control" 
                                       style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;"
                                       value="<?= htmlspecialchars($data['hometown']) ?>">
                            </div>

                            <div style="text-align: left; margin-bottom: 20px;">
                                <label style="font-size: 12px; font-weight: 700; color: #64748b; display:block; margin-bottom:4px;">Motto / Bio</label>
                                <textarea name="motto" rows="3" 
                                          style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; resize:none; font-family:inherit;"
                                          ><?= htmlspecialchars($data['motto']) ?></textarea>
                            </div>

                            <button type="submit" style="width: 100%; background: #0f172a; color: white; border: none; padding: 12px; border-radius: 8px; font-weight:600; cursor: pointer;">
                                Save Changes
                            </button>
                        </form>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        // UPDATED: Image Preview Function
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('profilePreview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

</body>
</html>