<?php
session_start();

// Redirect logged-in users
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    
    // 1. Special Redirect for Audience (Voters)
    if ($_SESSION['role'] === 'Audience') {
        header("Location: ./audience_dashboard.php");
        exit();
    }

    // 2. Redirect Staff & Participants
    switch ($_SESSION['role']) {
        case 'Event Manager':
            header("Location: ./dashboard.php");
            break;
        case 'Judge Coordinator':
            header("Location: ./judge_coordinator.php");
            break;
        case 'Contestant Manager':
            header("Location: ./contestant_manager.php"); 
            break;
        case 'Tabulator':
            header("Location: ./tabulator.php"); 
            break;
        case 'Contestant':
            header("Location: ./contestant_dashboard.php"); 
            break;
        case 'Judge': 
            header("Location: ./judge_dashboard.php"); 
            break;
        default:
            header("Location: ./logout.php");
            break;
    }
    exit();
}

$error = $_GET['error'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BPMS - Login</title>
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    
    <style>
        /* CORE RESET & EXISTING STYLES */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; height: 100vh; display: flex; overflow: hidden; }

        .brand-section {
            width: 50%;
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            color: white;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            padding: 40px; text-align: center; position: relative; z-index: 10;
        }

        .brand-header-group { display: flex; flex-direction: column; align-items: center; }
        .brand-text-group { margin-top: 20px; }
        .brand-logo { width: 140px; margin-bottom: 15px; }
        .brand-title { font-size: 36px; font-weight: 800; color: #F59E0B; letter-spacing: 1px; line-height: 1; }
        .brand-subtitle { font-size: 14px; color: #9ca3af; text-transform: uppercase; letter-spacing: 2px; margin-top: 5px; }
        .brand-tagline { font-size: 18px; color: #e5e7eb; font-style: italic; font-weight: 300; margin-bottom: 10px; }
        .brand-desc { font-size: 14px; color: #9ca3af; max-width: 400px; line-height: 1.5; margin: 0 auto; }
        .brand-footer { position: absolute; bottom: 20px; font-size: 12px; color: #6b7280; }

        .login-section {
            width: 50%; background-color: #f9fafb; display: flex; justify-content: center; align-items: center; position: relative;
        }

        .login-card {
            width: 100%; max-width: 420px; background: white; padding: 40px; border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05); margin: 20px;
        }

        .login-header h2 { font-size: 26px; color: #1f2937; margin-bottom: 5px; font-weight: 700; }
        .login-header p { color: #6b7280; font-size: 14px; margin-bottom: 30px; }

        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; color: #374151; font-size: 14px; font-weight: 600; }
        .input-wrapper { position: relative; }
        .input-wrapper i.icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 16px; }
        .form-control { width: 100%; padding: 12px 15px 12px 45px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; outline: none; transition: all 0.2s; background-color: #fff; }
        .form-control:focus { border-color: #F59E0B; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1); }

        .btn-login { width: 100%; padding: 14px; background-color: #F59E0B; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; transition: background 0.2s; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-login:hover { background-color: #d97706; }
        
        .register-link { text-align: center; margin-top: 20px; font-size: 14px; color: #6b7280; padding-bottom: 10px; border-bottom: 1px solid #f3f4f6; }
        .register-link a { color: #1f2937; font-weight: 700; text-decoration: none; margin-left: 5px; }
        .register-link a:hover { color: #F59E0B; text-decoration: underline; }

        .help-link { text-align: center; margin-top: 15px; font-size: 13px; color: #9ca3af; }
        .help-link a { color: #6b7280; font-weight: 600; text-decoration: none; }
        .login-footer { margin-top: 25px; text-align: center; font-size: 12px; color: #9ca3af; }
        .alert-error { background-color: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; text-align: center; border: 1px solid #fecaca; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #9ca3af; }

        /* TOGGLE STYLES */
        .hidden { display: none; }
        .audience-section { margin-top: 15px; text-align: center; }
        .audience-btn { background: none; border: none; color: #059669; font-weight: 700; cursor: pointer; text-decoration: underline; font-size: 14px; }
        .audience-btn:hover { color: #047857; }
        
        @media (max-width: 900px) {
            body { flex-direction: column; overflow-y: auto; }
            .brand-section { width: 100%; min-height: auto; padding: 15px 20px; flex-direction: row; justify-content: space-between; align-items: center; text-align: left; background: #111827; flex-shrink: 0; }
            .brand-header-group { flex-direction: row; align-items: center; gap: 15px; }
            .brand-logo { width: 45px; margin-bottom: 0; }
            .brand-title-wrapper { display: flex; flex-direction: column; }
            .brand-title { font-size: 22px; }
            .brand-subtitle { font-size: 10px; color: #d1d5db; letter-spacing: 1px; }
            .brand-text-group { display: none; margin-top: 0; text-align: right; }
            .brand-footer { display: none; }
            .login-section { width: 100%; padding: 20px; flex-grow: 1; align-items: flex-start; }
            .login-card { margin-top: 10px; }
        }
    </style>
</head>
<body>

    <div class="brand-section">
        <div class="brand-header-group">
            <img src="./assets/images/BPMS_logo.png" alt="BPMS Logo" class="brand-logo">
            <div class="brand-title-wrapper">
                <div class="brand-title">BPMS</div>
                <div class="brand-subtitle">Beauty Pageant Management System</div>
            </div>
        </div>
        <div class="brand-text-group">
            <div class="brand-tagline">"Celebrating Beauty, Intelligence, and Grace"</div>
            <p class="brand-desc">The official management portal for the University Beauty Pageant. Securely manage contestants in real-time.</p>
        </div>
        <div class="brand-footer">&copy; <?= date("Y") ?> UEP Beauty Pageant Management System.</div>
    </div>

    <div class="login-section">
        <div class="login-card">
            
            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div id="staff-form">
                <div class="login-header">
                    <h2>Welcome Back</h2>
                    <p>Enter your credentials to access your dashboard.</p>
                </div>

                <form action="../api/auth.php" method="POST">
                    <div class="input-group">
                        <label>Select Role</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user-tag icon"></i>
                            <select name="role" class="form-control" required>
                                <option value="" disabled selected>Select your role...</option>
                                <optgroup label="Management">
                                    <option value="Event Manager">Event Manager</option>
                                    <option value="Judge Coordinator">Judge Coordinator</option>
                                    <option value="Contestant Manager">Contestant Manager</option>
                                    <option value="Tabulator">Tabulator</option>
                                </optgroup>
                                <optgroup label="Participants">
                                    <option value="Judge">Judge</option>
                                    <option value="Contestant">Contestant</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope icon"></i>
                            <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock icon"></i>
                            <input type="password" name="password" id="loginPass" class="form-control" placeholder="••••••••" required>
                            <i class="fas fa-eye toggle-password" onclick="toggleLoginPassword()"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">
                        Sign In <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <div class="register-link">
                    Want to join the pageant? <a href="register.php">Register as Contestant</a>
                </div>

                <div class="audience-section">
                    Watching the show? <button class="audience-btn" onclick="toggleAudience(true)">Enter Ticket Code</button>
                </div>
                
                </div>

            <div id="audience-form" class="hidden">
                <div class="login-header">
                    <h2 style="color: #059669;">Audience Voting</h2>
                    <p>Enter your unique ticket code to proceed.</p>
                </div>

                <form action="../api/auth_ticket.php" method="POST">
                    <div class="input-group">
                        <label>Ticket Code</label>
                        <div class="input-wrapper">
                            <i class="fas fa-ticket-alt icon"></i>
                            <input type="text" name="ticket_code" class="form-control" placeholder="e.g. TICKET-123" required autocomplete="off" style="text-transform: uppercase; letter-spacing: 1px; font-weight: bold;">
                        </div>
                    </div>

                    <button type="submit" class="btn-login" style="background-color: #059669;">
                        Validate Ticket <i class="fas fa-check-circle"></i>
                    </button>
                </form>

                <div class="audience-section">
                    <button class="audience-btn" style="color: #6b7280;" onclick="toggleAudience(false)">← Back to Staff Login</button>
                </div>
            </div>

            <div class="help-link">
                Need Help? <a href="#" onclick="alert('Please contact the Event Manager for access.'); return false;">Contact Admin</a>
            </div>
            <div class="login-footer">Protected by <strong>BPMS Security</strong></div>
        </div>
    </div>

    <script>
        function toggleLoginPassword() {
            const input = document.getElementById('loginPass');
            const icon = document.querySelector('.toggle-password');
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        // Script to toggle forms
        function toggleAudience(showAudience) {
            const staffForm = document.getElementById('staff-form');
            const audienceForm = document.getElementById('audience-form');

            if (showAudience) {
                staffForm.classList.add('hidden');
                audienceForm.classList.remove('hidden');
            } else {
                audienceForm.classList.add('hidden');
                staffForm.classList.remove('hidden');
            }
        }
    </script>

</body>
</html>