<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();       // Ensures they have a Ticket ID (stored in user_id)
requireRole('Audience');

require_once __DIR__ . '/../app/config/database.php';

$ticket_id = $_SESSION['user_id'];

// 2. Get Ticket Status (Check if already voted)
$t_stmt = $conn->prepare("SELECT voted_contestant_id FROM tickets WHERE id = ?");
$t_stmt->bind_param("i", $ticket_id);
$t_stmt->execute();
$ticket_data = $t_stmt->get_result()->fetch_assoc();
$has_voted = !is_null($ticket_data['voted_contestant_id']);

// 3. Handle Voting
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote_id'])) {
    if (!$has_voted) {
        $vote_id = intval($_POST['vote_id']);
        
        // Update ticket: Set voted contestant AND timestamp
        $update = $conn->prepare("UPDATE tickets SET voted_contestant_id = ?, used_at = NOW() WHERE id = ?");
        $update->bind_param("ii", $vote_id, $ticket_id);
        
        if ($update->execute()) {
            $has_voted = true; // Lock UI immediately
            $msg = "Vote successfully cast! Thank you.";
        } else {
            $msg = "Error processing vote.";
        }
    }
}


$sql = "SELECT cd.*, u.name 
        FROM contestant_details cd 
        JOIN users u ON cd.user_id = u.id 
        JOIN events e ON cd.event_id = e.id
        WHERE u.status = 'Active' 
          AND e.status = 'Active' 
        ORDER BY cd.id ASC";

$contestants = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audience Voting | BPMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f3f4f6; margin: 0; padding-bottom: 50px; }
        
        /* Navbar */
        .navbar { background: #111827; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .logo { font-weight: 800; font-size: 20px; color: #F59E0B; }
        .btn-logout { background: transparent; border: 1px solid #ef4444; color: #ef4444; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-size: 14px; font-weight: bold; transition: 0.3s; }
        .btn-logout:hover { background: #ef4444; color: white; }

        .container { max-width: 1000px; margin: 30px auto; padding: 0 15px; }

        /* Messages */
        .status-box { background: white; padding: 25px; border-radius: 12px; text-align: center; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .success-msg { background: #d1fae5; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; border: 1px solid #a7f3d0; }

        /* Grid */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 25px; }
        
        /* Card */
        .card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.2s; position: relative; }
        .card:hover { transform: translateY(-5px); }
        .photo { width: 100%; height: 280px; object-fit: cover; background: #e5e7eb; }
        .info { padding: 20px; text-align: center; }
        .name { font-size: 18px; font-weight: 700; color: #1f2937; margin-bottom: 5px; }
        .motto { font-size: 13px; color: #6b7280; font-style: italic; margin-bottom: 15px; display: block; }
        .stats { font-size: 12px; color: #9ca3af; margin-bottom: 15px; border-top: 1px solid #f3f4f6; padding-top: 10px; }

        /* Buttons */
        .btn-vote { width: 100%; padding: 12px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; background: #111827; color: white; font-size: 14px; }
        .btn-vote:hover { background: #F59E0B; }
        
        /* Disabled State */
        .voted-btn { background: #e5e7eb; color: #9ca3af; cursor: not-allowed; }
        .voted-badge { background: #059669; color: white; padding: 12px; border-radius: 8px; font-weight: bold; display: block; }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="logo"><i class="fas fa-crown"></i> BPMS Audience</div>
        <a href="logout.php" class="btn-logout" onclick="return confirm('Warning: Logging out will invalidate your ticket. You will not be able to enter again. Continue?');">
            Logout <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>

    <div class="container">
        
        <?php if ($msg): ?>
            <div class="success-msg"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
        <?php endif; ?>

        <div class="status-box">
            <?php if ($has_voted): ?>
                <h2 style="color: #059669;">Vote Recorded</h2>
                <p>You have successfully cast your vote. You may browse the candidates, but voting is now closed.</p>
            <?php else: ?>
                <h2 style="color: #1f2937;">Cast Your Vote</h2>
                <p>Select your favorite contestant below. <strong>Note: You can only vote once.</strong></p>
            <?php endif; ?>
        </div>

        <div class="grid">
            <?php if ($contestants && $contestants->num_rows > 0): ?>
                <?php while($row = $contestants->fetch_assoc()): ?>
                    <div class="card">
                        <img src="./assets/uploads/contestants/<?= htmlspecialchars($row['photo']) ?>" class="photo" alt="Contestant">
                        
                        <div class="info">
                            <div class="name"><?= htmlspecialchars($row['name']) ?></div>
                            <span class="motto">"<?= htmlspecialchars($row['motto']) ?>"</span>
                            
                            <div class="stats">
                                <?= htmlspecialchars($row['age']) ?> yrs old • <?= htmlspecialchars($row['hometown']) ?>
                            </div>

                            <?php if (!$has_voted): ?>
                                <form method="POST" onsubmit="return confirm('Confirm vote for <?= htmlspecialchars($row['name']) ?>? This cannot be undone.');">
                                    <input type="hidden" name="vote_id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn-vote">Vote Now</button>
                                </form>
                            <?php else: ?>
                                <?php if ($ticket_data['voted_contestant_id'] == $row['id']): ?>
                                    <div class="voted-badge">VOTED <i class="fas fa-check"></i></div>
                                <?php else: ?>
                                    <button class="btn-vote voted-btn" disabled>Locked</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align:center; color:#6b7280; grid-column: 1 / -1;">No official candidates found for the active event.</p>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>