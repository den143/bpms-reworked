<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

// --- SECURITY HEADERS ---
// Prevent the browser from caching this page. 
// This ensures that if you Logout and click "Back", the browser is forced to reload
// (which triggers the login check and redirects you safely).
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 1. Fetch Active Event for this Manager
$manager_id = $_SESSION['user_id'];

$event_query = $conn->prepare("SELECT id, title, event_date, venue FROM events WHERE manager_id = ? AND status = 'Active' LIMIT 1");
$event_query->bind_param("i", $manager_id);
$event_query->execute();
$event_result = $event_query->get_result();

// ROBUSTNESS FIX: Check if an array was actually fetched
$active_event = $event_result->fetch_assoc(); 

// We rely on $active_event being not null, rather than ID being > 0
// This prevents the "ID 0" bug from hiding the dashboard.
$event_id = $active_event['id'] ?? null;
$event_title = $active_event['title'] ?? "No Active Event";
$event_venue = $active_event['venue'] ?? "No Venue Selected";

// DATE FORMATTING
$raw_date = $active_event['event_date'] ?? null;
$event_date_str = "TBA";
if ($raw_date) {
    $event_date_str = date("F d, Y", strtotime($raw_date));
}

// 2. FETCH REAL COUNTS (Only if event exists)
$count_contestants = 0;
$count_judges = 0;
$count_rounds = 0;
$count_criteria = 0;

if (!empty($active_event)) {
    // Count Contestants
    $c_stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM event_contestants 
        WHERE event_id = ? 
        AND status IN ('Active', 'Qualified', 'Eliminated') 
        AND is_deleted = 0
    ");
    $c_stmt->bind_param("i", $event_id);
    $c_stmt->execute();
    $count_contestants = $c_stmt->get_result()->fetch_assoc()['total'];

    // Count Judges
    $j_stmt = $conn->prepare("SELECT COUNT(*) as total FROM event_judges WHERE event_id = ? AND status = 'Active' AND is_deleted = 0");
    $j_stmt->bind_param("i", $event_id);
    $j_stmt->execute();
    $count_judges = $j_stmt->get_result()->fetch_assoc()['total'];
    
    // Count Rounds
    $r_stmt = $conn->prepare("SELECT COUNT(*) as total FROM rounds WHERE event_id = ? AND is_deleted = 0");
    $r_stmt->bind_param("i", $event_id);
    $r_stmt->execute();
    $count_rounds = $r_stmt->get_result()->fetch_assoc()['total'];

    // Count Criteria
    $crit_sql = "
        SELECT COUNT(c.id) as total 
        FROM criteria c
        JOIN segments s ON c.segment_id = s.id
        JOIN rounds r ON s.round_id = r.id
        WHERE r.event_id = ? AND c.is_deleted = 0 AND s.is_deleted = 0
    ";
    $crit_stmt = $conn->prepare($crit_sql);
    $crit_stmt->bind_param("i", $event_id);
    $crit_stmt->execute();
    $count_criteria = $crit_stmt->get_result()->fetch_assoc()['total'];
}

// Session messages
$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars($event_title) ?></title>
    <link rel="stylesheet" href="./assets/css/style.css?v=3">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    
    <style>
        /* Dashboard Specific Styles */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px; transition: transform 0.2s; border-bottom: 4px solid transparent; }
        .stat-card:hover { transform: translateY(-5px); border-bottom-color: #F59E0B; }
        .stat-icon { width: 60px; height: 60px; background-color: rgba(245, 158, 11, 0.1); color: #F59E0B; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 1.5rem; }
        .stat-info h3 { font-size: 24px; color: #111827; margin-bottom: 5px; }
        .stat-info p { font-size: 14px; color: #6b7280; margin: 0; }

        /* Setup Progress */
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .card-section { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card-title { font-size: 18px; font-weight: bold; color: #374151; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #f3f4f6; }
        .checklist { list-style: none; padding: 0; }
        .checklist-item { display: flex; align-items: center; padding: 15px 0; border-bottom: 1px solid #f9fafb; }
        .checklist-item:last-child { border-bottom: none; }
        
        .check-icon { width: 24px; height: 24px; border-radius: 50%; margin-right: 15px; display: flex; justify-content: center; align-items: center; font-size: 12px; }
        .check-icon.done { background-color: #D1FAE5; color: #059669; }
        .check-icon.pending { background-color: #FEE2E2; color: #DC2626; }
        
        .task-content strong { display: block; color: #374151; font-size: 15px; }
        .task-content span { font-size: 13px; color: #9ca3af; }
        
        .btn-action { display: inline-block; margin-left: auto; padding: 6px 12px; background-color: #f3f4f6; color: #374151; text-decoration: none; border-radius: 6px; font-size: 12px; transition: background 0.2s; }
        .btn-action:hover { background-color: #e5e7eb; }
        
        /* Quick Actions */
        .quick-actions-list { display: flex; flex-direction: column; gap: 10px; }
        .btn-quick { display: flex; align-items: center; justify-content: flex-start; padding: 12px 15px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; transition: all 0.2s; }
        .btn-quick i { margin-right: 10px; width: 20px; text-align: center; }
        .btn-quick.primary { background-color: #F59E0B; color: white; }
        .btn-quick.primary:hover { background-color: #d97706; transform: translateX(5px); }
        .btn-quick.secondary { background-color: #f3f4f6; color: #374151; }
        .btn-quick.secondary:hover { background-color: #e5e7eb; color: #111827; transform: translateX(5px); }

        /* IMPROVED MODAL DESIGN */
        .modal-overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(17, 24, 39, 0.85); /* Darker backdrop */
            backdrop-filter: blur(4px);
            display: none; justify-content: center; align-items: center; 
            z-index: 2000; 
        }
        .modal-content { 
            background: white; 
            padding: 40px; 
            width: 100%; 
            max-width: 480px; 
            border-radius: 16px; 
            text-align: left;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: modalPop 0.3s ease-out;
        }

        @keyframes modalPop {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-header { text-align: center; margin-bottom: 25px; }
        .modal-icon { 
            width: 70px; height: 70px; 
            background: #FFFBEB; color: #F59E0B; 
            border-radius: 50%; margin: 0 auto 15px; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 30px; 
        }
        
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 14px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .form-control:focus { border-color: #F59E0B; outline: none; box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2); }
        
        .btn-create-event { 
            background: #F59E0B; color: white; width: 100%; 
            padding: 14px; border: none; border-radius: 8px; 
            font-weight: bold; font-size: 16px; cursor: pointer; 
            transition: background 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-create-event:hover { background: #D97706; }

        @media (max-width: 900px) { .dashboard-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    
    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="main-wrapper">
        
        <?php include '../app/views/partials/sidebar.php'; ?>
        
        <div class="content-area">
            
            <div class="navbar">
                <div style="display:flex; align-items:center; gap: 15px;">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="navbar-title">Dashboard</div>
                </div>
            </div>

            <div class="container">

                <div id="toast-container" class="toast-container"></div>

                <div class="event-header">
                    <h1><?= htmlspecialchars($event_title) ?></h1>
                    <div class="event-meta">
                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($event_venue) ?></span>
                        <span style="margin: 0 10px;">|</span>
                        <span><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($event_date_str) ?></span>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-female"></i></div>
                        <div class="stat-info">
                            <h3><?= $count_contestants ?></h3>
                            <p>Contestants</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-gavel"></i></div>
                        <div class="stat-info">
                            <h3><?= $count_judges ?></h3>
                            <p>Judges</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-list-ol"></i></div>
                        <div class="stat-info">
                            <h3><?= $count_rounds ?></h3>
                            <p>Rounds</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                        <div class="stat-info">
                            <h3 style="font-size: 18px;"><?= $event_date_str ?></h3>
                            <p>Event Date</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    
                    <div class="card-section">
                        <div class="card-title">Setup Checklist</div>
                        <ul class="checklist">
                            <li class="checklist-item">
                                <div class="check-icon done"><i class="fas fa-check"></i></div>
                                <div class="task-content">
                                    <strong>Create Event</strong>
                                    <span>Event details created.</span>
                                </div>
                                <a href="settings.php" class="btn-action">View</a>
                            </li>

                            <li class="checklist-item">
                                <div class="check-icon <?= $count_contestants > 0 ? 'done' : 'pending' ?>">
                                    <i class="fas <?= $count_contestants > 0 ? 'fa-check' : 'fa-exclamation' ?>"></i>
                                </div>
                                <div class="task-content">
                                    <strong>Register Contestants</strong>
                                    <span><?= $count_contestants ?> Candidates added.</span>
                                </div>
                                <a href="contestants.php" class="btn-action">Add</a>
                            </li>

                            <li class="checklist-item">
                                <div class="check-icon <?= $count_judges > 0 ? 'done' : 'pending' ?>">
                                    <i class="fas <?= $count_judges > 0 ? 'fa-check' : 'fa-exclamation' ?>"></i>
                                </div>
                                <div class="task-content">
                                    <strong>Register Judges</strong>
                                    <span><?= $count_judges ?> Judges added.</span>
                                </div>
                                <a href="judges.php" class="btn-action">Add</a>
                            </li>

                            <li class="checklist-item">
                                <div class="check-icon <?= $count_rounds > 0 ? 'done' : 'pending' ?>">
                                    <i class="fas <?= $count_rounds > 0 ? 'fa-check' : 'fa-exclamation' ?>"></i>
                                </div>
                                <div class="task-content">
                                    <strong>Setup Rounds</strong>
                                    <span><?= $count_rounds ?> Rounds configured.</span>
                                </div>
                                <a href="rounds.php" class="btn-action">Setup</a>
                            </li>

                            <li class="checklist-item">
                                <div class="check-icon <?= $count_criteria > 0 ? 'done' : 'pending' ?>">
                                    <i class="fas <?= $count_criteria > 0 ? 'fa-check' : 'fa-exclamation' ?>"></i>
                                </div>
                                <div class="task-content">
                                    <strong>Setup Segments & Criteria</strong>
                                    <span><?= $count_criteria ?> Scoring Criteria set.</span>
                                </div>
                                <a href="criteria.php" class="btn-action">Setup</a>
                            </li>
                        </ul>
                    </div>

                    <div class="card-section">
                        <div class="card-title">Quick Actions</div>
                        
                        <?php if(!empty($active_event)): ?>
                            <div class="quick-actions-list">
                                
                                <a href="live_screen.php" target="_blank" class="btn-quick primary">
                                    <i class="fas fa-desktop"></i> Open Results Screen
                                </a>

                                <a href="tabulator.php" class="btn-quick primary">
                                    <i class="fas fa-table"></i> Tabulation Sheet
                                </a>

                                <a href="print_report.php" class="btn-quick secondary">
                                    <i class="fas fa-print"></i> Generate Reports
                                </a>
                                <a href="organizers.php" class="btn-quick secondary">
                                    <i class="fas fa-users-cog"></i> Manage Staff
                                </a>
                                <a href="settings.php" class="btn-quick secondary">
                                    <i class="fas fa-cogs"></i> Event Settings
                                </a>
                            </div>
                        <?php else: ?>
                            <p style="color: #6b7280; font-size: 14px; margin-bottom: 15px;">Create an event to unlock actions.</p>
                            <button onclick="document.getElementById('createEventModal').style.display='flex'" class="btn-quick primary" style="justify-content:center;">
                                <i class="fas fa-plus-circle"></i> Create Event Now
                            </button>
                        <?php endif; ?>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <?php if (empty($active_event)): ?>
    <div id="createEventModal" class="modal-overlay" style="display:flex;">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <h2 style="font-size: 24px; color: #111827; margin-bottom: 5px;">Welcome to BPMS</h2>
                <p style="color: #6B7280; font-size: 15px;">Let's get started by creating your first event.</p>
            </div>
            
            <form action="../api/event.php" method="POST">
                <div class="form-group">
                    <label>Event Name</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g., Miss Universe 2026" required autofocus>
                </div>
                
                <div class="form-group">
                    <label>Event Date</label>
                    <input type="date" name="event_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Venue</label>
                    <input type="text" name="venue" class="form-control" placeholder="e.g., City Coliseum" required>
                </div>
                
                <button type="submit" class="btn-create-event">
                    <i class="fas fa-magic"></i> Create Event & Start
                </button>
            </form>

            <div style="margin-top: 20px; text-align: center; border-top: 1px solid #f3f4f6; padding-top: 15px;">
                <p style="font-size: 13px; color: #9ca3af; margin-bottom: 10px;">Not ready to start?</p>
                <a href="logout.php" onclick="return confirm('Are you sure you want to logout?');" style="color: #EF4444; text-decoration: none; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                    <i class="fas fa-sign-out-alt"></i> Cancel & Logout
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebar.style.left === '0px') {
                sidebar.style.left = '-280px'; 
                overlay.style.display = 'none';
            } else {
                sidebar.style.left = '0px'; 
                overlay.style.display = 'block';
            }
        }

        // Simple Toast Logic
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icon = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-circle"></i>';
            toast.innerHTML = `${icon} <span>${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => { toast.remove(); }, 500);
            }, 3000);
        }

        <?php if ($success): ?> showToast("<?= htmlspecialchars($success) ?>", "success"); <?php endif; ?>
        <?php if ($error): ?> showToast("<?= htmlspecialchars($error) ?>", "error"); <?php endif; ?>
    </script>

</body>
</html>