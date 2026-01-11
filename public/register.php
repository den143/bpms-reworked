<?php
session_start();
require_once __DIR__ . '/../app/config/database.php';

// Fetch Active Events
$events = [];
$result = $conn->query("SELECT id, name FROM events WHERE status = 'Active'");
if ($result) {
    $events = $result->fetch_all(MYSQLI_ASSOC);
}

$error = $_GET['error'] ?? null;
$success = $_GET['success'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Candidate Registration - BPMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reuse core styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; height: 100vh; display: flex; overflow: hidden; }

        /* --- DESKTOP LAYOUT (50-50 Split) --- */
        .brand-section {
            width: 50%; /* 50-50 Split on Laptop */
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .brand-logo { width: 120px; margin-bottom: 20px; }
        .brand-text-container { display: flex; flex-direction: column; align-items: center; }
        .brand-title { font-size: 32px; font-weight: bold; color: #F59E0B; }
        .brand-subtitle { font-size: 18px; font-weight: 500; color: #e5e7eb; margin-bottom: 10px; }
        .brand-desc { font-size: 14px; color: #9ca3af; max-width: 350px; line-height: 1.5; }

        .form-section {
            width: 50%; /* 50-50 Split on Laptop */
            background-color: #f9fafb;
            overflow-y: auto;
            padding: 40px;
            display: flex;
            justify-content: center;
            align-items: flex-start; /* Aligns card to top when scrolling */
        }

        .register-card {
            width: 100%;
            max-width: 650px;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-top: 20px;
            margin-bottom: 40px;
        }

        /* --- FORM STYLES --- */
        .form-header { margin-bottom: 25px; text-align: center; }
        .form-header h2 { color: #1f2937; font-size: 26px; }
        .form-header p { color: #6b7280; font-size: 14px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }

        .form-group { position: relative; } 
        .form-group label { display: block; margin-bottom: 5px; color: #374151; font-weight: 600; font-size: 13px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; outline: none; }
        .form-control:focus { border-color: #F59E0B; }
        
        /* Remove arrows from number input */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; 
            margin: 0; 
        }

        /* UPLOAD BUTTON STYLE */
        .btn-upload {
            background-color: #9ca3af; /* Grey */
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s;
        }
        .btn-upload:hover { background-color: #6b7280; }
        .file-name-display { font-size: 13px; color: #6b7280; font-style: italic; margin-left: 10px; }

        .btn-submit { width: 100%; padding: 12px; background-color: #F59E0B; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 20px; font-size: 16px; }
        .btn-submit:hover { background-color: #d97706; }

        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; font-size: 14px; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #d1fae5; color: #059669; border: 1px solid #a7f3d0; }

        .back-link { display: block; text-align: center; margin-top: 20px; color: #6b7280; text-decoration: none; font-size: 14px; }
        .back-link:hover { color: #1f2937; }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 32px; 
            cursor: pointer;
            color: #9ca3af;
        }
        .toggle-password:hover { color: #374151; }

        /* --- MOBILE RESPONSIVENESS (The Fix) --- */
        @media (max-width: 900px) {
            body { 
                flex-direction: column; /* Stack top to bottom */
                overflow-y: auto;       /* Allow full page scroll */
            }

            /* BRAND SECTION BECOMES HEADER */
            .brand-section { 
                width: 100%; 
                min-height: 90px;      /* Fixed small header height */
                padding: 15px 20px; 
                flex-direction: row;    /* Row: Logo Left | Text Right */
                justify-content: flex-start; /* Align left */
                align-items: center;
                text-align: left;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                z-index: 10;
            }

            .brand-logo { 
                width: 50px; 
                margin-bottom: 0;       /* No bottom margin */
                margin-right: 15px;     /* Space between logo and text */
            }

            .brand-text-container {
                align-items: flex-start; /* Align text to left */
            }

            .brand-title { font-size: 20px; line-height: 1.2; }
            .brand-subtitle { font-size: 12px; margin-bottom: 0; opacity: 0.9; }
            .brand-desc { display: none; } /* Hide long description on mobile */

            /* FORM SECTION FILLS REST */
            .form-section { width: 100%; padding: 20px; }
            .register-card { margin-top: 0; padding: 25px; }
            .form-grid { grid-template-columns: 1fr; } /* Stack inputs */
            .full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>

    <div class="brand-section">
        <img src="./assets/images/BPMS_logo.png" alt="Logo" class="brand-logo">
        <div class="brand-text-container">
            <div class="brand-title">BPMS</div>
            <div class="brand-subtitle">Beauty Pageant Management System</div>
            <p class="brand-desc">Join the most prestigious pageant. Register your application today.</p>
        </div>
    </div>

    <div class="form-section">
        <div class="register-card">
            
            <div class="form-header">
                <h2>Candidate Registration</h2>
                <p>Please fill in your details correctly.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php elseif ($success): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                    <br><a href="index.php" style="font-weight:bold; color:inherit; text-decoration:underline;">Return to Login</a>
                </div>
            <?php endif; ?>

            <form action="../api/register_contestant.php" method="POST" enctype="multipart/form-data">
                
                <div class="form-grid">
                    
                    <div class="form-group full-width">
                        <label>Select Pageant Event</label>
                        <select name="event_id" class="form-control" required>
                            <option value="" disabled selected>-- Choose an Open Event --</option>
                            <?php foreach ($events as $evt): ?>
                                <option value="<?= $evt['id'] ?>"><?= htmlspecialchars($evt['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Maria Clara" required>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Create Password</label>
                        <input type="password" name="password" id="regPass" class="form-control" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('regPass', this)"></i>
                    </div>

                    <div class="form-group">
                        <label>Age</label>
                        <input type="number" name="age" class="form-control" min="16" max="30" required>
                    </div>

                    <div class="form-group">
                        <label>Height (cm)</label>
                        <input type="number" name="height" class="form-control" placeholder="170" required>
                    </div>

                    <div class="form-group">
                        <label>Vital Statistics</label>
                        <input type="text" name="vital_stats" class="form-control" placeholder="e.g. 34-24-36">
                    </div>

                    <div class="form-group">
                        <label>Hometown / Representing</label>
                        <input type="text" name="hometown" class="form-control" placeholder="e.g. Catarman" required>
                    </div>

                    <div class="form-group full-width">
                        <label>Advocacy / Motto</label>
                        <input type="text" name="motto" class="form-control" placeholder="Short phrase describing you...">
                    </div>

                    <div class="form-group full-width">
                        <label>Upload Photo (Headshot/Half Body)</label>
                        <div style="display: flex; align-items: center;">
                            <button type="button" class="btn-upload" onclick="document.getElementById('photoInput').click()">
                                <i class="fas fa-upload"></i> Choose Photo
                            </button>
                            <span id="fileNameDisplay" class="file-name-display">No file chosen</span>
                        </div>
                        <input type="file" name="photo" id="photoInput" style="display:none;" accept="image/*" onchange="updateFileName(this)" required>
                    </div>

                </div>

                <button type="submit" class="btn-submit">Submit Application</button>

                <a href="index.php" class="back-link">← Back to Login</a>

            </form>
        </div>
    </div>

    <script>
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
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

        function updateFileName(input) {
            const display = document.getElementById('fileNameDisplay');
            if (input.files && input.files.length > 0) {
                display.innerText = input.files[0].name;
                display.style.color = "#374151"; 
            } else {
                display.innerText = "No file chosen";
                display.style.color = "#6b7280";
            }
        }
    </script>

</body>
</html>