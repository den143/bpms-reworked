<?php
// app/views/partials/sidebar.php
// Purpose: Dynamic navigation menu that changes based on the logged-in User Role.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="assets/images/BPMS_logo.png" alt="BPMS Logo" class="sidebar-logo">
        <div class="brand-text">
            <div class="brand-name">BPMS</div>
            <div class="brand-subtitle"><?= htmlspecialchars($role) ?></div>
        </div>
    </div>
    
    <ul class="sidebar-menu">
        
        <?php if ($role === 'Event Manager'): ?>
            <li>
                <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="organizers.php" class="<?= $current_page == 'organizers.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-tie"></i> <span>Manage Team</span>
                </a>
            </li>
            <li>
                <a href="contestants.php" class="<?= $current_page == 'contestants.php' ? 'active' : '' ?>">
                    <i class="fas fa-female"></i> <span>Contestants</span>
                </a>
            </li>
            <li>
                <a href="judges.php" class="<?= $current_page == 'judges.php' ? 'active' : '' ?>">
                    <i class="fas fa-gavel"></i> <span>Judges</span>
                </a>
            </li>
            <li>
                <a href="rounds.php" class="<?= $current_page == 'rounds.php' ? 'active' : '' ?>">
                    <i class="fas fa-layer-group"></i> <span>Rounds & Flow</span>
                </a>
            </li>
            <li>
                <a href="criteria.php" class="<?= $current_page == 'criteria.php' ? 'active' : '' ?>">
                    <i class="fas fa-list-alt"></i> <span>Segments & Criteria</span>
                </a>
            </li>
            <li>
                <a href="activities.php" class="<?= $current_page == 'activities.php' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-check"></i> <span>Activities</span>
                </a>
            </li>
            <li>
                <a href="awards.php" class="<?= $current_page == 'awards.php' ? 'active' : '' ?>">
                    <i class="fas fa-trophy"></i> <span>Special Awards</span>
                </a>
            </li>
            <li>
                <a href="tabulator.php" class="<?= $current_page == 'tabulator.php' ? 'active' : '' ?>">
                    <i class="fas fa-print"></i> <span>Tabulation & Reports</span>
                </a>
            </li>

        <?php elseif ($role === 'Contestant Manager'): ?>
            <li>
                <a href="contestant_manager.php" class="<?= $current_page == 'contestant_manager.php' ? 'active' : '' ?>">
                    <i class="fas fa-users-cog"></i> <span>Manage Contestants</span>
                </a>
            </li>

        <?php elseif ($role === 'Judge Coordinator'): ?>
            <li>
                <a href="judge_coordinator.php" class="<?= $current_page == 'judge_coordinator.php' ? 'active' : '' ?>">
                    <i class="fas fa-clipboard-check"></i> <span>Monitor Judges</span>
                </a>
            </li>

        <?php elseif ($role === 'Tabulator'): ?>
            <li>
                <a href="tabulator.php" class="<?= $current_page == 'tabulator.php' ? 'active' : '' ?>">
                    <i class="fas fa-calculator"></i> <span>Live Tabulation</span>
                </a>
            </li>

        <?php elseif ($role === 'Judge'): ?>
            <li>
                <a href="judge_dashboard.php" class="<?= $current_page == 'judge_dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-star"></i> <span>Score Sheet</span>
                </a>
            </li>

        <?php elseif ($role === 'Contestant'): ?>
            <li>
                <a href="contestant_dashboard.php" class="<?= $current_page == 'contestant_dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-user"></i> <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="activities.php" class="<?= $current_page == 'activities.php' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> <span>Schedule</span>
                </a>
            </li>
        
        <?php endif; ?>

    </ul>
    
    <div class="sidebar-footer">
        <?php if ($role === 'Event Manager'): ?>
            <a href="settings.php" class="<?= $current_page == 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i> <span>Settings</span>
            </a>
        <?php endif; ?>
        
        <a href="logout.php" onclick="return confirm('Are you sure you want to logout?');">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>
</div>