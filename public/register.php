<?php
session_start();
require_once __DIR__ . '/../app/config/database.php';

// Fetch Active Events
$events = [];
if (isset($conn) && $conn) {
    $result = $conn->query("SELECT id, title FROM events WHERE status = 'Active'");
    if ($result) {
        $events = $result->fetch_all(MYSQLI_ASSOC);
    }
}

$error = $_GET['error'] ?? null;
$success = $_GET['success'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Candidate Registration - BPMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        /* --- CORE RESETS --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            height: 100vh; 
            display: flex; 
            overflow: hidden; 
            background-color: #f3f4f6;
        }

        /* --- LEFT SIDE: BRANDING --- */
        .brand-section {
            width: 50%;
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            text-align: center;
        }
        .brand-logo { width: 120px; margin-bottom: 20px; }
        .brand-text-container { display: flex; flex-direction: column; align-items: center; }
        .brand-title { font-size: 32px; font-weight: bold; color: #F59E0B; }
        .brand-subtitle { font-size: 18px; font-weight: 500; color: #e5e7eb; margin-bottom: 10px; }
        .brand-desc { font-size: 14px; color: #9ca3af; max-width: 350px; line-height: 1.5; }

        /* --- RIGHT SIDE: FORM --- */
        .form-section {
            width: 50%;
            background-color: #f9fafb;
            overflow-y: auto; 
            padding: 40px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .register-card {
            width: 100%;
            max-width: 650px;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 40px;
        }

        /* --- FORM GRID & INPUTS --- */
        .form-header { margin-bottom: 25px; text-align: center; }
        .form-header h2 { color: #1f2937; font-size: 26px; }
        .form-header p { color: #6b7280; font-size: 14px; }

        .form-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
        }

        /* --- THE MAGIC FIX FOR GRID --- */
        /* This tells the grid items they are allowed to be smaller than their content */
        .form-grid > div {
            min-width: 0;
        }

        .full-width { grid-column: span 2; }
        .triple-column { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }

        .form-group { position: relative; margin-bottom: 15px; } 
        .form-group label { display: block; margin-bottom: 5px; color: #374151; font-weight: 600; font-size: 13px; }
        
        .form-control { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #d1d5db; 
            border-radius: 6px; 
            outline: none; 
            font-size: 14px;
        }
        .form-control:focus { border-color: #F59E0B; }
        
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }

        /* --- STRONGER UPLOAD CONTAINER FIX --- */
        .upload-container {
            display: flex;
            align-items: center;
            gap: 10px; 
            width: 100%;
            border: 1px solid #d1d5db;
            padding: 5px;
            border-radius: 6px;
            background: white;
            /* Ensure this container never overflows its parent */
            max-width: 100%; 
            overflow: hidden;
        }

        .btn-upload {
            background-color: #9ca3af;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap; 
            flex-shrink: 0; /* Button stays fixed width */
        }

        .file-name-display { 
            font-size: 13px; 
            color: #6b7280; 
            font-style: italic;
            margin-left: 10px;
            
            /* FORCES TEXT TO BEHAVE */
            display: inline-block;
            max-width: 150px; /* Strict limit for mobile */
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis; 
            vertical-align: middle;
            
            @media (max-width: 900px) {
                .file-name-display {
                    max-width: 120px; /* Even smaller on phones */
                }
            }
        }

        .btn-submit { width: 100%; padding: 12px; background-color: #F59E0B; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 10px; font-size: 16px; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #6b7280; text-decoration: none; font-size: 14px; }
        
        .toggle-password { position: absolute; right: 10px; top: 32px; cursor: pointer; color: #9ca3af; }

        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; font-size: 14px; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #d1fae5; color: #059669; border: 1px solid #a7f3d0; }

        /* --- MOBILE RESPONSIVENESS --- */
        @media (max-width: 900px) {
            body { 
                flex-direction: column; 
                overflow-x: hidden;
                overflow-y: auto;
            }

            .brand-section { 
                width: 100%; 
                padding: 15px 20px; 
                flex-direction: row;    
                justify-content: flex-start;
                align-items: center;
                text-align: left;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                flex-shrink: 0;
            }

            .brand-logo { width: 40px; margin: 0 15px 0 0; }
            .brand-title { font-size: 18px; line-height: 1.2; }
            .brand-subtitle { font-size: 12px; margin: 0; opacity: 0.9; }
            .brand-desc { display: none; } 

            .form-section { 
                width: 100%; 
                padding: 15px; 
                display: block; 
            } 
            
            .register-card { 
                padding: 20px; 
                margin: 0;
                width: 100%;
                /* Double check width constraints */
                max-width: 100vw; 
                box-sizing: border-box;
            } 
            
            .form-grid { 
                grid-template-columns: 1fr; 
                gap: 15px; 
                /* Ensure grid itself doesn't overflow */
                width: 100%;
                max-width: 100%;
            } 
            
            .full-width { grid-column: span 1; }
            .triple-column { grid-template-columns: 1fr 1fr 1fr; gap: 8px; } 
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
                                <option value="<?= $evt['id'] ?>"><?= htmlspecialchars($evt['title']) ?></option>
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
                        <input type="number" step="0.01" name="height" class="form-control" placeholder="170" required>
                    </div>

                    <div class="form-group full-width">
                        <label>Vital Statistics (inches)</label>
                        <div class="triple-column">
                            <input type="number" step="0.1" name="bust" class="form-control" placeholder="Bust" required>
                            <input type="number" step="0.1" name="waist" class="form-control" placeholder="Waist" required>
                            <input type="number" step="0.1" name="hips" class="form-control" placeholder="Hips" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Hometown</label>
                        <input type="text" name="hometown" class="form-control" placeholder="e.g. Catarman" required>
                    </div>

                    <div class="form-group">
                        <label>Advocacy / Motto</label>
                        <input type="text" name="motto" class="form-control" placeholder="Short phrase...">
                    </div>

                    <div class="form-group full-width">
                        <label>Upload Photo</label>
                        <div class="upload-container">
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
                let name = input.files[0].name;
                
                // Manual JS Truncation to guarantee it fits
                if (name.length > 15) {
                    name = name.substring(0, 12) + "...";
                }
                
                display.innerText = name;
                display.style.color = "#374151"; 
            } else {
                display.innerText = "No file chosen";
                display.style.color = "#6b7280";
            }
        }
    </script>

</body>
</html>