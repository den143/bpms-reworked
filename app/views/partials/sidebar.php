<?php
// app/views/partials/sidebar.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = $_SESSION['role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" style="display: flex; flex-direction: column; overflow: hidden;">
    
    <div class="sidebar-header" style="flex-shrink: 0;">
        <img src="assets/images/BPMS_logo.png" alt="BPMS Logo" class="sidebar-logo">
        <div class="brand-text">
            <div class="brand-name">BPMS</div>
            <div class="brand-subtitle"><?= htmlspecialchars($role) ?></div>
        </div>
    </div>
    
    <ul class="sidebar-menu" style="flex-grow: 1; overflow-y: auto; margin: 0; padding: 0;">
        
        <?php if ($role === 'Event Manager'): ?>
            <li><a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i> <span>Dashboard</span></a></li>
            <li><a href="organizers.php" class="<?= $current_page == 'organizers.php' ? 'active' : '' ?>"><i class="fas fa-user-tie"></i> <span>Manage Organizers</span></a></li>
            <li><a href="contestants.php" class="<?= $current_page == 'contestants.php' ? 'active' : '' ?>"><i class="fas fa-female"></i> <span>Register Contestants</span></a></li>
            <li><a href="judges.php" class="<?= $current_page == 'judges.php' ? 'active' : '' ?>"><i class="fas fa-gavel"></i> <span>Manage Judges</span></a></li>
            <li><a href="rounds.php" class="<?= $current_page == 'rounds.php' ? 'active' : '' ?>"><i class="fas fa-layer-group"></i> <span>Manage Rounds</span></a></li>
            <li><a href="criteria.php" class="<?= $current_page == 'criteria.php' ? 'active' : '' ?>"><i class="fas fa-list-alt"></i> <span>Segments & Criteria</span></a></li>
            <li><a href="activities.php" class="<?= $current_page == 'activities.php' ? 'active' : '' ?>"><i class="fas fa-calendar-check"></i> <span>Manage Activities</span></a></li>
            <li><a href="awards.php" class="<?= $current_page == 'awards.php' ? 'active' : '' ?>"><i class="fas fa-trophy"></i> <span>Manage Awards</span></a></li>
            <li><a href="tabulator.php" class="<?= $current_page == 'tabulator.php' ? 'active' : '' ?>"><i class="fas fa-print"></i> <span>Tabulation & Reports</span></a></li>

        <?php elseif ($role === 'Contestant Manager'): ?>
            <li><a href="contestant_manager.php" class="<?= $current_page == 'contestant_manager.php' ? 'active' : '' ?>"><i class="fas fa-users-cog"></i> <span>Manage Contestants</span></a></li>

        <?php elseif ($role === 'Judge Coordinator'): ?>
            <li><a href="judge_coordinator.php" class="<?= $current_page == 'judge_coordinator.php' ? 'active' : '' ?>"><i class="fas fa-clipboard-check"></i> <span>Monitor Judges</span></a></li>

        <?php elseif ($role === 'Tabulator'): ?>
            <li><a href="tabulator.php" class="<?= $current_page == 'tabulator.php' ? 'active' : '' ?>"><i class="fas fa-calculator"></i> <span>Live Tabulation</span></a></li>

        <?php elseif ($role === 'Judge'): ?>
            <li><a href="judge_dashboard.php" class="<?= $current_page == 'judge_dashboard.php' ? 'active' : '' ?>"><i class="fas fa-star"></i> <span>Score Sheet</span></a></li>

        <?php endif; ?>
        
        <li style="height: 50px;"></li>
    </ul>
    
    <div class="sidebar-footer" style="flex-shrink: 0; padding: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
    <?php if ($role === 'Event Manager'): ?>
        <a href="settings.php" class="<?= $current_page == 'settings.php' ? 'active' : '' ?>" style="display: flex; align-items: center; margin-bottom: 15px; text-decoration: none;">
            <i class="fas fa-cog" style="width: 25px;"></i> <span>Settings</span>
        </a>
    <?php endif; ?>
    
    <a href="logout.php" onclick="return confirm('Are you sure you want to logout?');" style="display: flex; align-items: center; color: #ef4444; text-decoration: none;">
        <i class="fas fa-sign-out-alt" style="width: 25px;"></i> <span>Logout</span>
    </a>
</div>
</div>

<?php if ($role === 'Event Manager'): ?>
<script>
    function smartEmailQueue() {
        const cooldown = 60000; // 60 seconds allowed between checks
        const lastRun = localStorage.getItem('bpms_last_email_run');
        const now = Date.now();

        // Check if enough time has passed since the last run
        if (!lastRun || (now - lastRun) > cooldown) {
            
            // 1. Update timestamp immediately so subsequent calls (from fast clicking) get blocked
            localStorage.setItem('bpms_last_email_run', now);

            // 2. Run the Fetch
            console.log("System: Processing Email Queue...");
            fetch('../api/process_emails.php')
                .then(res => res.text())
                .then(data => console.log("Queue Result:", data))
                .catch(err => console.error("Queue Skipped (Network/Busy)"));
        } else {
            console.log("System: Email Queue on Cooldown");
        }
    }

    // Attempt to run on every page load (but the cooldown will block 99% of them)
    smartEmailQueue();

    // Also try every 60 seconds while staying on the same page
    setInterval(smartEmailQueue, 60000);
</script>
<?php endif; ?>